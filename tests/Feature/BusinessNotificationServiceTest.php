<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PushDevice;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CompanyContext;
use App\Services\FirebaseHttpV1Sender;
use App\Support\FirebasePushResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Covers the owner's explicit rules for the real-time business-event push
 * notification feature: strict per-company isolation (a company's alerts
 * never reach another company's users, "কঠোরভাবে হ্যান্ডেল করবে"),
 * permission-gated delivery, and "new company" being Super-Admin-only.
 *
 * Every test does its Company/User/PushDevice setup BEFORE installing the
 * FirebaseHttpV1Sender mock -- setup-time model events (a company being
 * created also fires CompanyNotificationObserver, for example) then run
 * against the real sender, which safely no-ops in the test environment
 * (Firebase is never configured here), so the mock's call-count
 * expectations only have to account for the action actually under test.
 */
class BusinessNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_notifies_only_the_owning_companys_permitted_users(): void
    {
        $companyA = $this->company('Alert Co A');
        $companyB = $this->company('Alert Co B');
        // manager role has notifications.orders by default.
        $managerA = $this->staff($companyA, 'manager');
        $managerB = $this->staff($companyB, 'manager');
        $this->device($managerA, 'token-a');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock) use ($companyA): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(fn (string $token, string $title, string $body, array $data): bool => $token === 'token-a'
                    && $title === 'New order received'
                    && $data['kind'] === 'business-alert'
                    && $data['alert_kind'] === 'order.created'
                    && $data['company_id'] === (string) $companyA->getKey())
                ->andReturn(FirebasePushResult::sent('msg-1'));
        });

        $this->order($companyA);

        $this->assertSame(1, $managerA->fresh()->notifications()->count());
        $this->assertSame(0, $managerB->fresh()->notifications()->count());
        $this->assertSame('order.created', $managerA->fresh()->notifications()->first()->data['alert_kind']);
    }

    public function test_order_created_skips_users_without_the_orders_permission(): void
    {
        $company = $this->company('No Orders Perm Co');
        $noPermissionRole = UserRole::query()->create([
            'name' => 'Warehouse Viewer',
            'slug' => 'warehouse_viewer',
            'permissions' => ['dashboard.view'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => $noPermissionRole->slug, 'is_active' => true]);
        $user->companies()->attach($company->getKey(), ['role' => $noPermissionRole->slug, 'is_default' => true]);

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldNotReceive('send');
        });

        $this->order($company);

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_order_status_change_notifies_permitted_users_on_every_transition(): void
    {
        $company = $this->company('Status Change Co');
        $manager = $this->staff($company, 'manager');
        $order = $this->order($company);
        $this->device($manager, 'status-token');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->twice()->andReturnTrue();
            $mock->shouldReceive('send')->twice()->andReturn(FirebasePushResult::sent('m'));
        });

        $order->update(['status' => 'confirmed']);
        $order->update(['status' => 'shipped']);

        $statusAlerts = $manager->fresh()->notifications
            ->filter(fn ($n) => ($n->data['alert_kind'] ?? null) === 'order.status_changed');

        $this->assertSame(2, $statusAlerts->count());
    }

    public function test_order_status_change_is_silent_when_neither_status_field_changed(): void
    {
        $company = $this->company('No-op Update Co');
        $this->staff($company, 'manager');
        $order = $this->order($company);

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            // A discount-only change never reaches BusinessNotificationService
            // at all -- OrderNotificationObserver::updated() bails out before
            // calling it when status/delivery_status didn't change.
            $mock->shouldNotReceive('isConfigured');
            $mock->shouldNotReceive('send');
        });

        $order->update(['discount' => 5]);

        $statusAlerts = User::query()->where('role', 'manager')->sole()->notifications
            ->filter(fn ($n) => ($n->data['alert_kind'] ?? null) === 'order.status_changed');

        $this->assertSame(0, $statusAlerts->count());
    }

    public function test_new_company_notifies_only_super_admins_never_regular_staff(): void
    {
        $existingCompany = $this->company('Existing Co');
        $manager = $this->staff($existingCompany, 'manager');
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldNotReceive('send');
        });

        $this->company('Brand New Co');

        $this->assertSame(1, $superAdmin->fresh()->notifications()->count());
        $this->assertSame('company.created', $superAdmin->fresh()->notifications()->first()->data['alert_kind']);
        $this->assertSame(0, $manager->fresh()->notifications()->count());
    }

    public function test_company_updated_notifies_only_that_companys_permitted_users(): void
    {
        $companyA = $this->company('Update Co A');
        $companyB = $this->company('Update Co B');
        $managerA = $this->staff($companyA, 'manager');
        $managerB = $this->staff($companyB, 'manager');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldNotReceive('send');
        });

        $companyA->update(['currency' => 'USD']);

        $alertsA = $managerA->fresh()->notifications
            ->filter(fn ($n) => ($n->data['alert_kind'] ?? null) === 'company.updated');

        $this->assertSame(1, $alertsA->count());
        $this->assertSame(0, $managerB->fresh()->notifications()->count());
    }

    public function test_new_staff_notifies_only_the_assigned_companys_permitted_users(): void
    {
        $companyA = $this->company('Staff Co A');
        $companyB = $this->company('Staff Co B');
        $managerA = $this->staff($companyA, 'manager');
        $managerB = $this->staff($companyB, 'manager');
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldNotReceive('send');
        });

        Livewire::actingAs($superAdmin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'New Hire',
                'email' => 'new-hire@example.test',
                'role' => 'sales_staff',
                'is_active' => true,
                'password' => 'a-strong-password-1',
                'company_ids' => [$companyA->getKey()],
                'default_company_id' => $companyA->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staffAlertsA = $managerA->fresh()->notifications
            ->filter(fn ($n) => ($n->data['alert_kind'] ?? null) === 'user.created');

        $this->assertSame(1, $staffAlertsA->count());
        $this->assertSame(
            0,
            $managerB->fresh()->notifications
                ->filter(fn ($n) => ($n->data['alert_kind'] ?? null) === 'user.created')
                ->count(),
        );
    }

    public function test_a_stale_push_token_is_deactivated_and_never_retried(): void
    {
        $company = $this->company('Stale Token Co');
        $manager = $this->staff($company, 'manager');
        $device = $this->device($manager, 'stale-token');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(FirebasePushResult::stale('UNREGISTERED', 'Token is no longer valid.'));
        });

        $this->order($company);

        $this->assertFalse($device->fresh()->is_active);
        $this->assertSame('UNREGISTERED', $device->fresh()->last_error_code);
    }

    public function test_database_notification_still_arrives_when_firebase_is_not_configured(): void
    {
        $company = $this->company('No Firebase Co');
        $manager = $this->staff($company, 'manager');
        $this->device($manager, 'irrelevant-token');

        $this->mock(FirebaseHttpV1Sender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnFalse();
            $mock->shouldNotReceive('send');
        });

        $this->order($company);

        $this->assertSame(1, $manager->fresh()->notifications()->count());
    }

    protected function company(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'invoice_prefix' => strtoupper(Str::random(4)),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    protected function staff(Company $company, string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->companies()->attach($company->getKey(), ['role' => $role, 'is_default' => true]);

        return $user;
    }

    protected function device(User $user, string $token): PushDevice
    {
        return PushDevice::query()->create([
            'user_id' => $user->getKey(),
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'device_id' => 'installation-'.hash('sha256', $token),
            'platform' => 'android',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
    }

    protected function order(Company $company): Order
    {
        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Business Alert Customer',
            'phone' => '+8801700000099',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Alert Product '.Str::random(6),
            'sku' => 'ALERT-'.Str::random(6),
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->getKey(),
            'type' => 'opening',
            'quantity' => 5,
            'reference_type' => Product::class,
            'reference_id' => $product->getKey(),
            'note' => 'Business alert test stock',
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'order_date' => now()->toDateString(),
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
            'status' => 'draft',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        app(CompanyContext::class)->clear();

        return $order;
    }
}
