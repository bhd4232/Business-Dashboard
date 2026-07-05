<?php

namespace Tests\Feature;

use App\Jobs\ProcessCourierWebhook;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CourierWebhookLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Alert dedupe uses the cache; isolate it per test so keys written
        // by earlier tests or previous runs (file store) cannot leak in.
        config(['cache.default' => 'array']);
    }

    public function test_sync_command_updates_active_bookings_across_companies_and_skips_terminal_and_recent(): void
    {
        $companyA = $this->company('Monitor Company A', 'monitor-company-a', 'MCA');
        $companyB = $this->company('Monitor Company B', 'monitor-company-b', 'MCB');
        $providerA = $this->redxProvider($companyA);
        $providerB = $this->redxProvider($companyB);

        $due = $this->booking($companyA, $providerA, 'RDX-A-1', CourierBooking::STATUS_IN_TRANSIT);
        $delivered = $this->booking($companyA, $providerA, 'RDX-A-2', CourierBooking::STATUS_DELIVERED);
        $recent = $this->booking($companyA, $providerA, 'RDX-A-3', CourierBooking::STATUS_BOOKED, lastSyncedAt: now()->subMinutes(2));
        $otherCompany = $this->booking($companyB, $providerB, 'RDX-B-1', CourierBooking::STATUS_BOOKED);

        Http::fake([
            'openapi.redx.com.bd/v1.0.0-beta/parcel/info/*' => Http::response([
                'parcel' => ['status' => 'delivered'],
            ]),
        ]);

        $this->artisan('couriers:sync-statuses')
            ->expectsOutputToContain('synced: 2, failed: 0')
            ->assertSuccessful();

        $this->assertSame(CourierBooking::STATUS_DELIVERED, $due->fresh()->status);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $otherCompany->fresh()->status);
        $this->assertSame(CourierBooking::STATUS_BOOKED, $recent->fresh()->status);
        $this->assertNotNull($due->fresh()->last_synced_at);
        $this->assertSame(0, $providerA->fresh()->sync_failure_count);
    }

    public function test_sync_command_respects_batch_limit(): void
    {
        $company = $this->company('Batch Company', 'batch-company', 'BAT');
        $provider = $this->redxProvider($company, ['sync_batch_limit' => 1]);
        $this->booking($company, $provider, 'RDX-L-1', CourierBooking::STATUS_BOOKED);
        $this->booking($company, $provider, 'RDX-L-2', CourierBooking::STATUS_BOOKED);

        Http::fake([
            'openapi.redx.com.bd/v1.0.0-beta/parcel/info/*' => Http::response([
                'parcel' => ['status' => 'delivered'],
            ]),
        ]);

        $this->artisan('couriers:sync-statuses')
            ->expectsOutputToContain('synced: 1, failed: 0')
            ->assertSuccessful();
    }

    public function test_repeated_sync_failures_notify_admins_once_per_day(): void
    {
        $company = $this->company('Failure Company', 'failure-company', 'FLR');
        $provider = $this->redxProvider($company, ['sync_failure_alert_threshold' => 2]);
        $admin = $this->superAdmin();

        $bookingOne = $this->booking($company, $provider, 'RDX-F-1', CourierBooking::STATUS_BOOKED);
        $bookingTwo = $this->booking($company, $provider, 'RDX-F-2', CourierBooking::STATUS_BOOKED);

        Http::fake([
            'openapi.redx.com.bd/v1.0.0-beta/parcel/info/*' => Http::response(['message' => 'server error'], 500),
        ]);

        $this->artisan('couriers:sync-statuses')->assertSuccessful();

        $provider->refresh();
        $this->assertSame(2, $provider->sync_failure_count);
        $this->assertNotNull($provider->last_sync_error);
        $this->assertSame(1, $admin->notifications()->count());

        // Second run keeps failing but the alert is deduplicated for the day.
        $bookingOne->forceFill(['last_synced_at' => now()->subHours(2)])->saveQuietly();
        $bookingTwo->forceFill(['last_synced_at' => now()->subHours(2)])->saveQuietly();

        $this->artisan('couriers:sync-statuses')->assertSuccessful();

        $this->assertSame(4, $provider->fresh()->sync_failure_count);
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_stale_bookings_trigger_an_aggregated_alert(): void
    {
        $company = $this->company('Stale Company', 'stale-company', 'STL');
        $provider = $this->redxProvider($company, ['stale_after_days' => 3]);
        $admin = $this->superAdmin();

        $stale = $this->booking($company, $provider, 'RDX-S-1', CourierBooking::STATUS_IN_TRANSIT, lastSyncedAt: now());
        $stale->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        Http::fake();

        $this->artisan('couriers:sync-statuses')->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('Stale courier bookings', $admin->notifications()->first()->data['title'] ?? '');
    }

    public function test_permanently_failed_webhook_notifies_admins(): void
    {
        $company = $this->company('Webhook Company', 'webhook-company', 'WHK');
        $provider = $this->redxProvider($company);
        $admin = $this->superAdmin();

        app(CompanyContext::class)->set($company);
        $log = CourierWebhookLog::query()->create([
            'company_id' => $company->getKey(),
            'courier_provider_id' => $provider->getKey(),
            'event' => 'status-update',
            'delivery_id' => 'whk-1',
            'payload' => ['tracking_id' => 'MISSING'],
            'status' => 'failed',
            'attempts' => 5,
            'error' => 'Booking not found.',
        ]);
        app(CompanyContext::class)->clear();

        (new ProcessCourierWebhook($log->getKey()))->failed(new \RuntimeException('Booking not found.'));

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('Courier webhook failed', $admin->notifications()->first()->data['title'] ?? '');
    }

    public function test_alerts_only_reach_managers_of_the_owning_company(): void
    {
        $companyA = $this->company('Alert Company A', 'alert-company-a', 'ALA');
        $companyB = $this->company('Alert Company B', 'alert-company-b', 'ALB');
        $provider = $this->redxProvider($companyA, ['stale_after_days' => 3]);

        $managerA = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $managerA->companies()->attach($companyA->getKey(), ['role' => 'manager', 'is_default' => true]);
        $managerB = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $managerB->companies()->attach($companyB->getKey(), ['role' => 'manager', 'is_default' => true]);

        $stale = $this->booking($companyA, $provider, 'RDX-I-1', CourierBooking::STATUS_IN_TRANSIT, lastSyncedAt: now());
        $stale->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        Http::fake();

        $this->artisan('couriers:sync-statuses')->assertSuccessful();

        $this->assertSame(1, $managerA->notifications()->count());
        $this->assertSame(0, $managerB->notifications()->count());
    }

    protected function company(string $name, string $slug, string $prefix): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => $slug,
            'invoice_prefix' => $prefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    protected function redxProvider(Company $company, array $settings = []): CourierProvider
    {
        return CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'RedX',
            'slug' => 'redx-'.$company->getKey(),
            'driver' => CourierProvider::DRIVER_REDX,
            'credentials' => ['access_token' => 'redx-token'],
            'settings' => $settings,
            'is_active' => true,
        ]);
    }

    protected function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    protected function booking(
        Company $company,
        CourierProvider $provider,
        string $trackingId,
        string $status,
        ?\Illuminate\Support\Carbon $lastSyncedAt = null,
    ): CourierBooking {
        app(CompanyContext::class)->set($company);

        $order = $this->orderForCompany($company, $trackingId);

        $booking = CourierBooking::query()->create([
            'company_id' => $company->getKey(),
            'courier_provider_id' => $provider->getKey(),
            'order_id' => $order->getKey(),
            'tracking_id' => $trackingId,
            'recipient_name' => 'Monitor Customer',
            'recipient_phone' => '+8801700000002',
            'recipient_address' => 'Dhaka',
            'cod_amount' => 500,
            'status' => $status,
            'booked_at' => now()->subDay(),
            'last_synced_at' => $lastSyncedAt,
        ]);

        app(CompanyContext::class)->clear();

        return $booking;
    }

    protected function orderForCompany(Company $company, string $suffix): Order
    {
        $customer = Customer::query()->create([
            'name' => 'Monitor Customer',
            'phone' => '+8801700000002',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Monitor Product '.$suffix,
            'sku' => 'MONITOR-'.$suffix,
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
            'note' => 'Monitoring test stock',
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

        return $order;
    }
}
