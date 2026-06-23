<?php

namespace Tests\Feature;

use App\Jobs\ProcessCourierWebhook;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CourierStatusLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\CourierManager;
use App\Services\CourierService;
use App\Services\SteadfastCourierClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourierIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_manager_resolves_manual_adapter_and_creates_booking(): void
    {
        $company = $this->company('Manager Company', 'manager-company', 'MGR');
        app(CompanyContext::class)->set($company);
        $order = $this->orderForCompany($company);
        $provider = app(CourierService::class)->manualProvider($company);

        $booking = app(CourierManager::class)->create($order, $provider, ['tracking_id' => 'MGR-001']);

        $this->assertSame('MGR-001', $booking->tracking_id);
        $this->assertSame($company->getKey(), $booking->company_id);
    }

    public function test_signed_courier_webhook_is_idempotently_queued(): void
    {
        Queue::fake();
        $company = $this->company('Webhook Company', 'webhook-company', 'WHK');
        app(CompanyContext::class)->set($company);
        $provider = $this->steadfastProvider($company);
        $credentials = $provider->credentials;
        $credentials['webhook_secret'] = 'webhook-test-secret';
        $provider->update(['credentials' => $credentials]);

        $payload = json_encode(['event_id' => 'evt-001', 'tracking_code' => 'TRACK-001', 'delivery_status' => 'delivered']);
        $signature = hash_hmac('sha256', $payload, 'webhook-test-secret');
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_COURIER_SIGNATURE' => $signature, 'HTTP_X_WEBHOOK_ID' => 'evt-001'];

        $this->call('POST', route('couriers.webhook', $provider), [], [], [], $server, $payload)->assertAccepted();
        $this->call('POST', route('couriers.webhook', $provider), [], [], [], $server, $payload)->assertAccepted();

        $this->assertDatabaseCount('courier_webhook_logs', 1);
        Queue::assertPushed(ProcessCourierWebhook::class, 1);
    }

    public function test_manual_courier_booking_updates_order_delivery_status_without_changing_order_status(): void
    {
        $company = $this->company('Gift Items', 'gift-items-courier', 'GFT');
        app(CompanyContext::class)->set($company);
        $order = $this->orderForCompany($company);

        $booking = app(CourierService::class)->createManualBooking($order, [
            'tracking_id' => 'MANUAL-001',
            'recipient_name' => 'Courier Customer',
            'recipient_phone' => '+8801700000000',
            'recipient_address' => 'Dhaka',
            'cod_amount' => 500,
            'note' => 'Manual test booking.',
        ]);

        $this->assertSame($company->getKey(), $booking->company_id);
        $this->assertSame(CourierBooking::STATUS_BOOKED, $booking->status);
        $this->assertSame(CourierBooking::STATUS_BOOKED, $order->refresh()->delivery_status);
        $this->assertSame('completed', $order->status);
        $this->assertDatabaseHas('courier_providers', [
            'company_id' => $company->getKey(),
            'slug' => 'manual',
        ]);
        $this->assertDatabaseHas('courier_status_logs', [
            'courier_booking_id' => $booking->getKey(),
            'from_status' => null,
            'to_status' => CourierBooking::STATUS_BOOKED,
        ]);
    }

    public function test_manual_courier_status_updates_are_logged_and_company_scoped(): void
    {
        $garments = $this->company('Garments Machinery', 'garments-courier', 'GM');
        $solar = $this->company('Solar Items', 'solar-courier', 'SOL');

        app(CompanyContext::class)->set($garments);
        $garmentsBooking = app(CourierService::class)->createManualBooking($this->orderForCompany($garments));

        app(CompanyContext::class)->set($solar);
        app(CourierService::class)->createManualBooking($this->orderForCompany($solar), [
            'tracking_id' => 'SOL-TRACK-001',
        ]);

        app(CompanyContext::class)->set($garments);
        app(CourierService::class)->updateStatus($garmentsBooking, CourierBooking::STATUS_DELIVERED, 'Delivered by rider.');

        $this->assertSame(CourierBooking::STATUS_DELIVERED, $garmentsBooking->refresh()->status);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $garmentsBooking->order->refresh()->delivery_status);
        $this->assertSame(1, CourierBooking::query()->count());
        $this->assertSame(2, CourierStatusLog::query()->count());

        app(CompanyContext::class)->set($solar);
        $this->assertSame(['SOL-TRACK-001'], CourierBooking::query()->pluck('tracking_id')->all());
    }

    public function test_manual_booking_can_use_selected_custom_provider(): void
    {
        $company = $this->company('Custom Courier Company', 'custom-courier-company', 'CCC');
        app(CompanyContext::class)->set($company);
        $order = $this->orderForCompany($company);

        $provider = CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Local Rider Team',
            'slug' => 'local-rider-team',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'settings' => [
                'contact_person' => 'Rider Manager',
                'phone' => '+8801711111111',
                'warehouse' => 'Main Warehouse',
                'delivery_fees' => [
                    'inside' => 50,
                    'outside' => 100,
                    'suburb' => 70,
                ],
            ],
            'credentials' => [],
            'is_active' => true,
        ]);

        $booking = app(CourierService::class)->createManualBooking($order, [
            'courier_provider_id' => $provider->getKey(),
            'tracking_id' => 'LOCAL-001',
        ]);

        $this->assertSame($provider->getKey(), $booking->courier_provider_id);
        $this->assertSame('Rider Manager', $booking->provider->settings['contact_person']);
        $this->assertSame(CourierBooking::STATUS_BOOKED, $order->refresh()->delivery_status);
    }

    public function test_courier_provider_from_another_company_cannot_be_used_for_manual_booking(): void
    {
        $orderCompany = $this->company('Order Company', 'order-company', 'ORD');
        $providerCompany = $this->company('Provider Company', 'provider-company', 'PRV');

        app(CompanyContext::class)->set($orderCompany);
        $order = $this->orderForCompany($orderCompany);

        app(CompanyContext::class)->set($providerCompany);
        $provider = CourierProvider::query()->create([
            'company_id' => $providerCompany->getKey(),
            'name' => 'Provider Company Courier',
            'slug' => 'provider-company-courier',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'credentials' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        app(CompanyContext::class)->all();

        $this->expectException(ValidationException::class);

        app(CourierService::class)->createManualBooking($order, [
            'courier_provider_id' => $provider->getKey(),
            'tracking_id' => 'WRONG-COMPANY',
        ]);
    }

    public function test_courier_provider_from_another_company_cannot_be_used_for_steadfast_booking(): void
    {
        $orderCompany = $this->company('Steadfast Order Company', 'steadfast-order-company', 'SOC');
        $providerCompany = $this->company('Steadfast Provider Company', 'steadfast-provider-company', 'SPC');

        app(CompanyContext::class)->set($orderCompany);
        $order = $this->orderForCompany($orderCompany);

        app(CompanyContext::class)->set($providerCompany);
        $provider = $this->steadfastProvider($providerCompany);

        app(CompanyContext::class)->all();

        $this->expectException(ValidationException::class);

        app(CourierService::class)->createSteadfastBooking($order, $provider, [
            'recipient_address' => 'Dhaka',
            'cod_amount' => 500,
        ]);
    }

    public function test_courier_admin_pages_render_for_sales_user(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);
        $company = Company::defaultCompany();
        $user->companies()->sync([
            $company->getKey() => [
                'role' => 'sales_staff',
                'is_default' => true,
            ],
        ]);
        app(CompanyContext::class)->set($company);

        app(CourierService::class)->createManualBooking($this->orderForCompany($company), [
            'tracking_id' => 'ADMIN-COURIER-001',
        ]);

        $this->actingAs($user)
            ->get('/admin/courier-bookings')
            ->assertOk()
            ->assertSee('ADMIN-COURIER-001');

        $this->actingAs($user)
            ->get('/admin/courier-providers')
            ->assertOk()
            ->assertSee('Manual Courier');

        $this->actingAs($user)
            ->get('/admin/courier-providers/create')
            ->assertOk()
            ->assertSee('Select Delivery Partner')
            ->assertSee('Set Delivery Fees');
    }

    public function test_courier_provider_create_is_blocked_when_all_companies_are_selected(): void
    {
        Company::defaultCompany();

        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => 'all'])
            ->get('/admin/courier-providers/create')
            ->assertForbidden();
    }

    public function test_steadfast_booking_posts_order_and_stores_consignment_response(): void
    {
        $company = $this->company('Steadfast Company', 'steadfast-company', 'STF');
        app(CompanyContext::class)->set($company);
        $order = $this->orderForCompany($company);
        $provider = $this->steadfastProvider($company);

        Http::fake([
            SteadfastCourierClient::DEFAULT_BASE_URL.'/create_order' => Http::response([
                'status' => 200,
                'message' => 'Consignment has been created successfully.',
                'consignment' => [
                    'consignment_id' => 1424107,
                    'invoice' => $order->order_number,
                    'tracking_code' => '15BAEB8A',
                    'recipient_name' => 'Courier Customer',
                    'recipient_phone' => '+8801700000000',
                    'recipient_address' => 'Dhaka',
                    'cod_amount' => 500,
                    'status' => 'in_review',
                ],
            ]),
        ]);

        $booking = app(CourierService::class)->createSteadfastBooking($order, $provider, [
            'recipient_address' => 'Dhaka',
            'cod_amount' => 500,
            'delivery_type' => 0,
        ]);

        $this->assertSame('15BAEB8A', $booking->tracking_id);
        $this->assertSame('1424107', $booking->provider_reference);
        $this->assertSame(CourierBooking::STATUS_BOOKING_PENDING, $booking->status);
        $this->assertSame(CourierBooking::STATUS_BOOKING_PENDING, $order->refresh()->delivery_status);

        Http::assertSent(function ($request) use ($order): bool {
            return $request->hasHeader('Api-Key', 'test-api-key')
                && $request->hasHeader('Secret-Key', 'test-secret-key')
                && $request['invoice'] === $order->order_number
                && $request['recipient_name'] === 'Courier Customer'
                && (float) $request['cod_amount'] === 500.0;
        });
    }

    public function test_steadfast_status_sync_maps_delivery_status(): void
    {
        $company = $this->company('Steadfast Sync Company', 'steadfast-sync-company', 'STS');
        app(CompanyContext::class)->set($company);
        $order = $this->orderForCompany($company);
        $provider = $this->steadfastProvider($company);

        $booking = CourierBooking::query()->create([
            'company_id' => $company->getKey(),
            'courier_provider_id' => $provider->getKey(),
            'order_id' => $order->getKey(),
            'tracking_id' => 'SYNC123',
            'provider_reference' => '999',
            'recipient_name' => 'Courier Customer',
            'recipient_phone' => '+8801700000000',
            'recipient_address' => 'Dhaka',
            'cod_amount' => 500,
            'status' => CourierBooking::STATUS_BOOKED,
            'booked_at' => now(),
        ]);

        Http::fake([
            SteadfastCourierClient::DEFAULT_BASE_URL.'/status_by_trackingcode/SYNC123' => Http::response([
                'status' => 200,
                'delivery_status' => 'delivered',
            ]),
        ]);

        app(CourierService::class)->syncSteadfastStatus($booking);

        $this->assertSame(CourierBooking::STATUS_DELIVERED, $booking->refresh()->status);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $order->refresh()->delivery_status);
        $this->assertDatabaseHas('courier_status_logs', [
            'courier_booking_id' => $booking->getKey(),
            'from_status' => CourierBooking::STATUS_BOOKED,
            'to_status' => CourierBooking::STATUS_DELIVERED,
        ]);
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

    protected function orderForCompany(Company $company): Order
    {
        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Courier Customer',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Courier Product '.$company->getKey(),
            'sku' => 'COURIER-SKU-'.$company->getKey(),
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
            'note' => 'Courier test stock',
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
        $order->update(['status' => 'completed']);

        return $order->refresh();
    }

    protected function steadfastProvider(Company $company): CourierProvider
    {
        return CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Steadfast',
            'slug' => 'steadfast',
            'driver' => CourierProvider::DRIVER_STEADFAST,
            'credentials' => [
                'api_key' => 'test-api-key',
                'secret_key' => 'test-secret-key',
            ],
            'settings' => [
                'base_url' => SteadfastCourierClient::DEFAULT_BASE_URL,
            ],
            'is_active' => true,
        ]);
    }
}
