<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\CompanyContext;
use App\Services\CourierManager;
use App\Services\SteadfastCourierClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LiveCourierAdaptersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pathao_booking_issues_cached_token_and_stores_consignment(): void
    {
        $company = $this->company('Pathao Company', 'pathao-company', 'PTH');
        $order = $this->orderForCompany($company);
        $provider = $this->provider($company, CourierProvider::DRIVER_PATHAO, [
            'client_id' => 'cid',
            'client_secret' => 'csecret',
            'username' => 'merchant@example.com',
            'password' => 'secret-pass',
        ], ['default_store_id' => 77]);

        Http::fake([
            'api-hermes.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'pathao-token',
                'expires_in' => 3600,
            ]),
            'api-hermes.pathao.com/aladdin/api/v1/orders' => Http::response([
                'message' => 'Order Created Successfully',
                'data' => ['consignment_id' => 'DL123456', 'order_status' => 'Pending'],
            ]),
            'api-hermes.pathao.com/aladdin/api/v1/orders/DL123456/info' => Http::response([
                'data' => ['order_status' => 'Delivered'],
            ]),
        ]);

        $booking = app(CourierManager::class)->create($order, $provider, [
            'recipient_city' => 1,
            'recipient_zone' => 2,
            'recipient_area' => 3,
        ]);

        $this->assertSame('DL123456', $booking->tracking_id);
        $this->assertSame(CourierBooking::STATUS_BOOKING_PENDING, $booking->status);
        $this->assertSame(77, Http::recorded()->filter(
            fn ($request): bool => str_contains($request[0]->url(), '/aladdin/api/v1/orders')
        )->first()[0]['store_id']);

        $synced = app(CourierManager::class)->sync($booking);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $synced->status);

        // Token issued exactly once across create + sync (cached).
        $tokenCalls = Http::recorded(fn ($request): bool => str_contains($request->url(), 'issue-token'))->count();
        $this->assertSame(1, $tokenCalls);
    }

    public function test_redx_booking_sends_access_token_header_and_syncs_status(): void
    {
        $company = $this->company('RedX Company', 'redx-company', 'RDX');
        $order = $this->orderForCompany($company);
        $provider = $this->provider($company, CourierProvider::DRIVER_REDX, [
            'access_token' => 'redx-token',
        ]);

        Http::fake([
            'openapi.redx.com.bd/v1.0.0-beta/parcel' => Http::response(['tracking_id' => '21RDX987654']),
            'openapi.redx.com.bd/v1.0.0-beta/parcel/info/21RDX987654' => Http::response([
                'parcel' => ['status' => 'delivered'],
            ]),
        ]);

        $booking = app(CourierManager::class)->create($order, $provider, [
            'delivery_area' => 'Banani',
            'delivery_area_id' => 12,
        ]);

        $this->assertSame('21RDX987654', $booking->tracking_id);
        $this->assertSame(CourierBooking::STATUS_BOOKED, $booking->status);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1.0.0-beta/parcel')
            && $request->hasHeader('API-ACCESS-TOKEN', 'Bearer redx-token'));

        $synced = app(CourierManager::class)->sync($booking);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $synced->status);
    }

    public function test_ecourier_booking_sends_credential_headers_and_syncs_status(): void
    {
        $company = $this->company('ECourier Company', 'ecourier-company', 'ECR');
        $order = $this->orderForCompany($company);
        $provider = $this->provider($company, CourierProvider::DRIVER_ECOURIER, [
            'api_key' => 'eck',
            'api_secret' => 'ecs',
            'user_id' => 'ecu',
        ], ['default_package_code' => 'PKG1']);

        Http::fake([
            'backoffice.ecourier.com.bd/api/order-place' => Http::response([
                'success' => true,
                'ID' => 'ECR123ABC',
                'message' => 'Order placed',
            ]),
            'backoffice.ecourier.com.bd/api/track' => Http::response([
                'status' => 'Delivered',
            ]),
        ]);

        $booking = app(CourierManager::class)->create($order, $provider, [
            'recipient_city' => 'Dhaka',
            'recipient_thana' => 'Banani',
            'recipient_zip' => '1213',
        ]);

        $this->assertSame('ECR123ABC', $booking->tracking_id);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'order-place')
            && $request->hasHeader('API-KEY', 'eck')
            && $request->hasHeader('API-SECRET', 'ecs')
            && $request->hasHeader('USER-ID', 'ecu'));

        $synced = app(CourierManager::class)->sync($booking);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $synced->status);
    }

    public function test_steadfast_balance_client_returns_current_balance(): void
    {
        $company = $this->company('Balance Company', 'balance-company', 'BAL');
        $provider = CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Steadfast',
            'slug' => 'steadfast-balance',
            'driver' => CourierProvider::DRIVER_STEADFAST,
            'credentials' => ['api_key' => 'k', 'secret_key' => 's'],
            'settings' => [],
            'is_active' => true,
        ]);

        Http::fake([
            'portal.packzy.com/api/v1/get_balance' => Http::response([
                'status' => 200,
                'current_balance' => 1250.50,
            ]),
        ]);

        $response = app(SteadfastCourierClient::class)->balance($provider);

        $this->assertSame(1250.5, (float) $response['current_balance']);
    }

    public function test_live_courier_provider_from_another_company_is_rejected(): void
    {
        $companyA = $this->company('Live Company A', 'live-company-a', 'LCA');
        $companyB = $this->company('Live Company B', 'live-company-b', 'LCB');
        $order = $this->orderForCompany($companyA);

        foreach ([CourierProvider::DRIVER_PATHAO, CourierProvider::DRIVER_REDX, CourierProvider::DRIVER_ECOURIER] as $driver) {
            $foreignProvider = $this->provider($companyB, $driver, [
                'client_id' => 'x', 'client_secret' => 'x', 'username' => 'x', 'password' => 'x',
                'access_token' => 'x', 'api_key' => 'x', 'api_secret' => 'x', 'user_id' => 'x',
            ]);

            app(CompanyContext::class)->set($companyA);

            try {
                app(CourierManager::class)->create($order, $foreignProvider, []);
                $this->fail("Expected {$driver} booking with another company's provider to fail.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
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

    protected function provider(Company $company, string $driver, array $credentials, array $settings = []): CourierProvider
    {
        return CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => CourierProvider::DRIVERS[$driver],
            'slug' => $driver.'-'.$company->getKey(),
            'driver' => $driver,
            'credentials' => $credentials,
            'settings' => $settings,
            'is_active' => true,
        ]);
    }

    protected function orderForCompany(Company $company): Order
    {
        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Live Courier Customer',
            'phone' => '+8801700000001',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Live Courier Product '.$company->getKey(),
            'sku' => 'LIVE-COURIER-'.$company->getKey(),
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
            'note' => 'Live courier test stock',
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

        return $order->fresh(['customer', 'items.product', 'company']);
    }
}
