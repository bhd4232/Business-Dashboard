<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_reorder_adds_previous_order_items_to_cart(): void
    {
        $company = $this->createPublishedStorefrontCompany('Reorder Store', 'reorder.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Rechargeable Fan', 'REORDER-FAN-001');

        $customer = Customer::query()->create([
            'name' => 'Repeat Buyer',
            'phone' => '01728174614',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 3,
            'unit_price' => 900,
        ]);

        $this->actingAs($customer, 'customer');

        $this->post("http://reorder.example.test/account/orders/{$order->order_number}/reorder")
            ->assertRedirect('http://reorder.example.test/cart');

        $this->get('http://reorder.example.test/cart')
            ->assertOk()
            ->assertSee('Rechargeable Fan');

        $this->get('http://reorder.example.test/account/orders')
            ->assertOk()
            ->assertSee('Reorder');
    }

    public function test_reorder_requires_the_owning_customer_and_same_company_storefront_order(): void
    {
        $company = $this->createPublishedStorefrontCompany('Reorder Guard Store', 'reorder-guard.example.test');
        $otherCompany = $this->createPublishedStorefrontCompany('Other Store', 'reorder-other.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Guarded Product', 'REORDER-GUARD-001');

        $customer = Customer::query()->create([
            'name' => 'Guard Buyer',
            'phone' => '01911111111',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $intruder = Customer::query()->create([
            'name' => 'Another Buyer',
            'phone' => '01999999999',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        // Guest requests do not reveal whether the order exists.
        $this->post("http://reorder-guard.example.test/account/orders/{$order->order_number}/reorder")
            ->assertRedirect('http://reorder-guard.example.test/account/login');

        // Another customer cannot reorder the owner's items.
        $this->actingAs($intruder, 'customer')
            ->post("http://reorder-guard.example.test/account/orders/{$order->order_number}/reorder")
            ->assertNotFound();

        // Another company's storefront cannot reorder this order.
        $this->actingAs($customer, 'customer')
            ->post("http://reorder-other.example.test/account/orders/{$order->order_number}/reorder")
            ->assertNotFound();
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 1000,
            'sale_price' => 900,
            'cost_price' => 600,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    private function createPublishedStorefrontCompany(string $name, string $domain): Company
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString().'-'.str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => str($name)->substr(0, 3)->upper()->toString(),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'whatsapp_number' => '+8801700000000',
            'meta_title' => $name,
            'is_published' => true,
        ]);

        return $company;
    }
}
