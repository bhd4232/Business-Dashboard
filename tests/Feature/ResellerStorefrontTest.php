<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ResellerProduct;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResellerStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_404s_for_an_unknown_pending_or_rejected_slug(): void
    {
        $company = $this->company('unknown.example.test');
        app(CompanyContext::class)->set($company);
        $pending = $this->reseller($company, 'pending', 'pending-slug');
        $rejected = $this->reseller($company, 'rejected', 'rejected-slug');

        $this->get('http://unknown.example.test/store/does-not-exist/products')->assertNotFound();
        $this->get('http://unknown.example.test/store/pending-slug/products')->assertNotFound();
        $this->get('http://unknown.example.test/store/rejected-slug/products')->assertNotFound();
    }

    public function test_store_404s_when_the_reseller_module_is_disabled(): void
    {
        $company = $this->company('module-disabled.example.test');
        $company->update(['reseller_module_enabled' => false]);
        app(CompanyContext::class)->set($company);
        $this->reseller($company, 'approved', 'live-slug');

        $this->get('http://module-disabled.example.test/store/live-slug/products')->assertNotFound();
    }

    public function test_product_listing_shows_only_the_resellers_active_picks(): void
    {
        $company = $this->company('catalog.example.test');
        app(CompanyContext::class)->set($company);
        $reseller = $this->reseller($company, 'approved', 'my-shop');

        $picked = $this->product($company, 'Picked Product');
        $hidden = $this->product($company, 'Hidden Product');
        $notPicked = $this->product($company, 'Not Picked Product');

        ResellerProduct::query()->create(['customer_id' => $reseller->getKey(), 'product_id' => $picked->getKey(), 'is_active' => true]);
        ResellerProduct::query()->create(['customer_id' => $reseller->getKey(), 'product_id' => $hidden->getKey(), 'is_active' => false]);

        $response = $this->get('http://catalog.example.test/store/my-shop/products');

        $response->assertOk()
            ->assertSee('Picked Product')
            ->assertDontSee('Hidden Product')
            ->assertDontSee('Not Picked Product');
    }

    public function test_the_main_storefront_still_shows_the_full_catalog_unaffected(): void
    {
        $company = $this->company('unaffected.example.test');
        app(CompanyContext::class)->set($company);
        $reseller = $this->reseller($company, 'approved', 'curated-shop');

        $picked = $this->product($company, 'Curated Product');
        $everythingElse = $this->product($company, 'Everything Else Product');

        ResellerProduct::query()->create(['customer_id' => $reseller->getKey(), 'product_id' => $picked->getKey(), 'is_active' => true]);

        // The plain, non-reseller storefront must still show every product,
        // proving the reseller-scoping change is additive, not global.
        $this->get('http://unaffected.example.test/products')
            ->assertOk()
            ->assertSee('Curated Product')
            ->assertSee('Everything Else Product');
    }

    public function test_an_order_placed_through_a_reseller_store_is_attributed_to_that_reseller(): void
    {
        $company = $this->company('attribution.example.test');
        app(CompanyContext::class)->set($company);
        $reseller = $this->reseller($company, 'approved', 'attributed-shop');
        $product = $this->product($company, 'Attributed Product');
        ResellerProduct::query()->create(['customer_id' => $reseller->getKey(), 'product_id' => $product->getKey(), 'is_active' => true]);

        $this->post("http://attribution.example.test/store/attributed-shop/cart/items/{$product->slug}", ['quantity' => 1])
            ->assertRedirect();

        $this->post('http://attribution.example.test/store/attributed-shop/checkout', [
            'name' => 'Store Buyer',
            'phone' => '01700333444',
            'email' => 'buyer2@example.test',
            'address' => 'Uttara, Dhaka',
        ])->assertRedirect();

        $order = Order::query()->where('source', Order::SOURCE_STOREFRONT)->first();

        $this->assertNotNull($order);
        $this->assertSame($reseller->getKey(), $order->reseller_customer_id);
    }

    public function test_a_regular_storefront_order_has_no_reseller_attribution(): void
    {
        $company = $this->company('no-attribution.example.test');
        app(CompanyContext::class)->set($company);
        $product = $this->product($company, 'Plain Product');

        $this->post("http://no-attribution.example.test/cart/items/{$product->slug}", ['quantity' => 1])
            ->assertRedirect();

        $this->post('http://no-attribution.example.test/checkout', [
            'name' => 'Plain Buyer',
            'phone' => '01700555666',
            'email' => 'buyer3@example.test',
            'address' => 'Banani, Dhaka',
        ])->assertRedirect();

        $order = Order::query()->where('source', Order::SOURCE_STOREFRONT)->first();

        $this->assertNotNull($order);
        $this->assertNull($order->reseller_customer_id);
    }

    public function test_adding_a_product_not_in_the_resellers_catalog_is_rejected(): void
    {
        $company = $this->company('not-in-catalog.example.test');
        app(CompanyContext::class)->set($company);
        $this->reseller($company, 'approved', 'strict-shop');
        $product = $this->product($company, 'Unlisted Product');

        $this->post("http://not-in-catalog.example.test/store/strict-shop/cart/items/{$product->slug}", ['quantity' => 1])
            ->assertNotFound();
    }

    protected function company(string $domain): Company
    {
        $company = Company::query()->create([
            'name' => 'Store Test '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => strtoupper(Str::random(4)),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
            'reseller_module_enabled' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
        ]);

        return $company;
    }

    protected function reseller(Company $company, string $status, string $slug): Customer
    {
        static $counter = 0;
        $counter++;

        return Customer::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Reseller '.$counter,
            'phone' => '0191000'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'reseller_status' => $status,
            'reseller_slug' => $slug,
            'business_name' => 'Shop '.$counter,
            'is_active' => true,
        ]);
    }

    protected function product(Company $company, string $name): Product
    {
        static $counter = 0;
        $counter++;

        return Product::query()->create([
            'company_id' => $company->getKey(),
            'name' => $name,
            'sku' => 'STORE-PROD-'.$counter,
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }
}
