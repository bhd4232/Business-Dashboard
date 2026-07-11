<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\ShippingFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_determine_zone_matches_configured_area_keywords(): void
    {
        $company = $this->companyWithZones();
        $service = app(ShippingFeeService::class);

        $this->assertSame('inside', $service->determineZone('House 12, Road 5, Gulshan, Dhaka', $company));
        $this->assertSame('outside', $service->determineZone('Agrabad, Chittagong', $company));
        $this->assertSame('suburb', $service->determineZone('Sector 3, Savar', $company));
        $this->assertNull($service->determineZone('Somewhere unlisted', $company));
        $this->assertNull($service->determineZone(null, $company));
    }

    public function test_fee_for_uses_first_active_courier_providers_delivery_fees(): void
    {
        $company = $this->companyWithZones();

        CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Custom',
            'slug' => 'custom',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'credentials' => [],
            'settings' => [
                'delivery_fees' => ['inside' => 60, 'outside' => 120, 'suburb' => 90],
            ],
            'is_active' => true,
        ]);

        $result = app(ShippingFeeService::class)->feeFor('Gulshan, Dhaka', $company);

        $this->assertSame('inside', $result['zone']);
        $this->assertSame(60.0, $result['fee']);
        $this->assertNotNull($result['courier_provider_id']);
    }

    public function test_fee_for_returns_zero_when_no_active_courier_provider_exists(): void
    {
        $company = $this->companyWithZones();

        $result = app(ShippingFeeService::class)->feeFor('Gulshan, Dhaka', $company);

        $this->assertSame('inside', $result['zone']);
        $this->assertSame(0.0, $result['fee']);
        $this->assertNull($result['courier_provider_id']);
    }

    public function test_creating_an_order_auto_populates_shipping_fee_and_folds_it_into_the_total(): void
    {
        $company = $this->companyWithZones();
        app(CompanyContext::class)->set($company);

        CourierProvider::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Custom',
            'slug' => 'custom',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'credentials' => [],
            'settings' => [
                'delivery_fees' => ['inside' => 60, 'outside' => 120, 'suburb' => 90],
            ],
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Zone Customer',
            'phone' => '+8801711112222',
            'address' => 'Gulshan, Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Shipping Fee Product',
            'sku' => 'SHIP-FEE-SKU',
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'company_id' => $company->getKey(),
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
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
            'unit_cost' => 300,
        ]);

        $order->refresh();

        $this->assertSame('inside', $order->shipping_zone);
        $this->assertSame('60.00', $order->shipping_fee);
        $this->assertSame('560.00', $order->total_amount);
    }

    protected function companyWithZones(): Company
    {
        return Company::query()->create([
            'name' => 'Zone Company '.uniqid(),
            'slug' => 'zone-company-'.uniqid(),
            'invoice_prefix' => 'ZC'.rand(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
            'settings' => [
                'shipping_zones' => [
                    'inside' => ['Gulshan', 'Dhanmondi'],
                    'outside' => ['Chittagong', 'Sylhet'],
                    'suburb' => ['Savar', 'Gazipur'],
                ],
            ],
        ]);
    }
}
