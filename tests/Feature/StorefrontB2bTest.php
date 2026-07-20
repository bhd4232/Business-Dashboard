<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontB2bTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_tier_price_applies_by_cart_quantity(): void
    {
        $company = $this->createPublishedStorefrontCompany('B2B Store', 'b2b.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Wholesale Fan', 'B2B-FAN-001', [
            'stock' => 100,
            'tier_prices' => [
                ['min_qty' => 10, 'price' => 45],
                ['min_qty' => 50, 'price' => 40],
            ],
        ]);

        $cart = app(StorefrontCart::class);

        $cart->add($company, $product, 5);
        $this->assertSame(50.0 * 5, $cart->subtotal($company)); // sale price 50

        $cart->update($company, $product, 10);
        $this->assertSame(45.0 * 10, $cart->subtotal($company));

        $cart->update($company, $product, 60);
        $this->assertSame(40.0 * 60, $cart->subtotal($company));
    }

    public function test_moq_is_enforced_when_adding_and_updating_cart(): void
    {
        $company = $this->createPublishedStorefrontCompany('MOQ Store', 'moq.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Bulk Cable', 'MOQ-CABLE-001', [
            'stock' => 100,
            'moq' => 5,
        ]);

        $cart = app(StorefrontCart::class);

        // Adding 1 is raised to the MOQ of 5.
        $cart->add($company, $product, 1);
        $this->assertSame(5, $cart->items($company)->first()['quantity']);

        // Updating below MOQ clamps back up to MOQ.
        $cart->update($company, $product, 2);
        $this->assertSame(5, $cart->items($company)->first()['quantity']);

        // Quantity 0 still removes the line.
        $cart->update($company, $product, 0);
        $this->assertTrue($cart->items($company)->isEmpty());
    }

    public function test_product_page_shows_wholesale_table_and_moq_badge(): void
    {
        $company = $this->createPublishedStorefrontCompany('Tier Page Store', 'tier-page.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Tiered Panel', 'TIER-PANEL-001', [
            'stock' => 100,
            'moq' => 5,
            'tier_prices' => [
                ['min_qty' => 10, 'price' => 45],
            ],
        ]);

        $this->get('http://tier-page.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Wholesale pricing')
            ->assertSee('Minimum order: 5')
            ->assertSee('BDT 45.00');
    }

    public function test_account_orders_page_does_not_leak_customer_balance(): void
    {
        $company = $this->createPublishedStorefrontCompany('Due Store', 'due.example.test');

        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Due Product', 'DUE-PRODUCT-001', ['stock' => 10]);

        $customer = Customer::query()->create([
            'name' => 'Due Buyer',
            'phone' => '01755555555',
            'opening_balance' => 1500,
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
            'unit_price' => 900,
        ]);

        // Account authentication protects the order list; customer balance is
        // still intentionally reserved for staff-facing ERP screens.
        $this->actingAs($customer, 'customer')
            ->get('http://due.example.test/account/orders')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertDontSee('Current due with')
            ->assertDontSee('1,500.00');
    }

    private function createProduct(string $name, string $sku, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => $name,
            'sku' => $sku,
            'price' => 55,
            'sale_price' => 50,
            'cost_price' => 30,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ], $overrides));
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
