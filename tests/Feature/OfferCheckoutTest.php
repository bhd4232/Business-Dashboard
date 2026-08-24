<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\OfferItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_combo_offer_checkout_explodes_into_order_items_for_every_component(): void
    {
        $company = $this->createStore('combo-checkout.example.test');
        app(CompanyContext::class)->set($company);

        $productA = $this->createProduct('Combo Item A', 'COMBO-A-1', 300, 10);
        $productB = $this->createProduct('Combo Item B', 'COMBO-B-1', 200, 5);
        $offer = $this->createCombo($company, 'Starter Combo', [
            [$productA, 1],
            [$productB, 1],
        ]);

        $this->post('http://combo-checkout.example.test/offers/'.$offer->slug.'/checkout', [
            'name' => 'Combo Buyer',
            'phone' => '01711000001',
            'address' => 'Dhaka',
            'quantity' => 2,
            'payment_method' => 'cod',
        ])->assertRedirectContains('/offers/'.$offer->slug.'/thank-you/');

        $order = Order::withoutGlobalScopes()->latest()->firstOrFail();
        $this->assertSame(Order::SOURCE_OFFER, $order->source);

        $items = OrderItem::withoutGlobalScopes()->where('order_id', $order->getKey())->get();
        $this->assertCount(2, $items);
        $this->assertSame(2, (int) $items->firstWhere('product_id', $productA->getKey())->quantity);
        $this->assertSame(2, (int) $items->firstWhere('product_id', $productB->getKey())->quantity);
    }

    public function test_offer_checkout_auto_creates_a_customer_login_for_a_new_phone(): void
    {
        $company = $this->createStore('combo-account.example.test');
        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Single Item', 'SINGLE-ACC-1', 500, 10);
        $offer = $this->createCombo($company, 'Single Offer', [[$product, 1]], Offer::TYPE_SINGLE);

        $this->post('http://combo-account.example.test/offers/'.$offer->slug.'/checkout', [
            'name' => 'New Account Buyer',
            'phone' => '01711000002',
            'address' => 'Dhaka',
            'quantity' => 1,
            'payment_method' => 'cod',
        ])->assertRedirectContains('/offers/'.$offer->slug.'/thank-you/');

        $customer = Customer::withoutGlobalScopes()->where('phone', '01711000002')->first();
        $this->assertNotNull($customer);
        $this->assertTrue($customer->isRegistered());
    }

    public function test_offer_checkout_does_not_duplicate_an_already_registered_customer(): void
    {
        $company = $this->createStore('combo-existing.example.test');
        app(CompanyContext::class)->set($company);

        Customer::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Existing Buyer',
            'phone' => '01711000003',
            'password' => bcrypt('secret-password'),
            'customer_type' => 'regular',
            'customer_source' => 'website',
            'is_active' => true,
        ]);

        $product = $this->createProduct('Single Item 2', 'SINGLE-ACC-2', 500, 10);
        $offer = $this->createCombo($company, 'Single Offer 2', [[$product, 1]], Offer::TYPE_SINGLE);

        $this->post('http://combo-existing.example.test/offers/'.$offer->slug.'/checkout', [
            'name' => 'Existing Buyer',
            'phone' => '01711000003',
            'address' => 'Dhaka',
            'quantity' => 1,
            'payment_method' => 'cod',
        ])->assertRedirectContains('/offers/'.$offer->slug.'/thank-you/');

        $this->assertSame(1, Customer::withoutGlobalScopes()->where('phone', '01711000003')->count());
    }

    public function test_offer_checkout_rejects_when_component_stock_is_insufficient(): void
    {
        $company = $this->createStore('combo-stock.example.test');
        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Low Stock Item', 'LOW-STOCK-1', 500, 1);
        $offer = $this->createCombo($company, 'Low Stock Offer', [[$product, 1]], Offer::TYPE_SINGLE);

        $this->post('http://combo-stock.example.test/offers/'.$offer->slug.'/checkout', [
            'name' => 'Stock Buyer',
            'phone' => '01711000004',
            'address' => 'Dhaka',
            'quantity' => 5,
            'payment_method' => 'cod',
        ])->assertStatus(422);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_offer_landing_page_and_index_render(): void
    {
        $company = $this->createStore('combo-landing.example.test');
        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Landing Item', 'LANDING-1', 500, 10);
        $offer = $this->createCombo($company, 'Landing Offer', [[$product, 1]], Offer::TYPE_SINGLE);

        $this->get('http://combo-landing.example.test/offers')
            ->assertOk()
            ->assertSee('Landing Offer');

        $this->get('http://combo-landing.example.test/offers/'.$offer->slug)
            ->assertOk()
            ->assertSee('Landing Offer');
    }

    public function test_admin_offers_list_renders_the_price_column_without_error(): void
    {
        $company = $this->createStore('combo-admin-list.example.test');
        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Admin List Item', 'ADMIN-LIST-1', 500, 10);
        $this->createCombo($company, 'Admin List Offer', [[$product, 1]], Offer::TYPE_SINGLE);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $user->companies()->attach($company, ['role' => 'super_admin', 'is_default' => true]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get('/admin/storefront/offers')
            ->assertOk()
            ->assertSee('Admin List Offer');
    }

    public function test_offer_preview_shows_a_draft_offer(): void
    {
        $company = $this->createStore('combo-preview.example.test');
        app(CompanyContext::class)->set($company);

        $product = $this->createProduct('Preview Item', 'PREVIEW-1', 500, 10);
        $offer = $this->createCombo($company, 'Preview Draft Offer', [[$product, 1]], Offer::TYPE_SINGLE);
        $offer->update(['status' => Offer::STATUS_DRAFT]);

        // The real public route 404s a draft — preview must not.
        $this->get('http://combo-preview.example.test/offers/'.$offer->slug)->assertNotFound();

        $this->get("http://127.0.0.1/storefront/{$company->slug}/offers/{$offer->slug}")
            ->assertOk()
            ->assertSee('Preview Draft Offer');
    }

    public function test_offer_preview_rejects_staff_from_another_company(): void
    {
        $ownCompany = $this->createStore('combo-preview-own.example.test');
        $otherCompany = $this->createStore('combo-preview-other.example.test');
        app(CompanyContext::class)->set($otherCompany);

        $product = $this->createProduct('Other Co Item', 'OTHERCO-1', 500, 10);
        $offer = $this->createCombo($otherCompany, 'Other Co Offer', [[$product, 1]]);

        $user = User::factory()->create(['role' => 'sales_staff', 'is_active' => true]);
        $user->companies()->attach($ownCompany, ['role' => 'sales_staff', 'is_default' => true]);

        $this->actingAs($user)
            ->get("http://127.0.0.1/storefront/{$otherCompany->slug}/offers/{$offer->slug}")
            ->assertNotFound();
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $components
     */
    protected function createCombo(Company $company, string $title, array $components, string $type = Offer::TYPE_COMBO): Offer
    {
        $offer = Offer::query()->create([
            'company_id' => $company->getKey(),
            'type' => $type,
            'title' => $title,
            'status' => Offer::STATUS_PUBLISHED,
            'price_mode' => Offer::PRICE_MODE_AUTO_SUM,
        ]);

        foreach ($components as $index => [$product, $qty]) {
            OfferItem::query()->create([
                'company_id' => $company->getKey(),
                'offer_id' => $offer->getKey(),
                'product_id' => $product->getKey(),
                'quantity' => $qty,
                'sort_order' => $index,
            ]);
        }

        return $offer->fresh(['items.product', 'items.productVariant']);
    }

    protected function createProduct(string $name, string $sku, float $price, int $stock): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => $price,
            'sale_price' => $price,
            'cost_price' => $price * 0.6,
            'stock' => $stock,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    protected function createStore(string $domain): Company
    {
        static $counter = 0;
        $counter++;

        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'OFR'.$counter,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
            'cod_enabled' => true,
        ]);

        return $company;
    }
}
