<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontVatAndDeliveryTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_vat_is_off_by_default_and_checkout_totals_are_unchanged(): void
    {
        $company = $this->createStore('vat-off.example.test');
        $product = $this->createProduct('VAT Off Product', 950);

        $order = $this->checkout('vat-off.example.test', $product, 2);

        $this->assertSame(0.0, (float) $order->vat);
        $this->assertSame(1970.0, (float) $order->total_amount); // 1900 + 70 inside-Dhaka delivery
        $this->assertFalse($company->storefrontSetting->vatActive());
    }

    public function test_exclusive_vat_is_added_on_top_of_the_product_subtotal_only(): void
    {
        $this->createStore('vat-exclusive.example.test', [
            'vat_enabled' => true,
            'vat_rate' => 5,
            'vat_mode' => StorefrontSetting::VAT_MODE_EXCLUSIVE,
        ]);
        $product = $this->createProduct('VAT Exclusive Product', 950);

        $this->post('http://vat-exclusive.example.test/cart/items/'.$product->slug, ['quantity' => 2]);
        $this->get('http://vat-exclusive.example.test/checkout')
            ->assertOk()
            ->assertSee('data-checkout-vat', false)
            ->assertSee('VAT (5%)')
            ->assertSee('vat: 95', false);

        $order = $this->checkout('vat-exclusive.example.test', $product, 0);

        $this->assertSame(95.0, (float) $order->vat); // 5% of 1900, delivery not taxed
        $this->assertSame(2065.0, (float) $order->total_amount);
    }

    public function test_inclusive_vat_only_shows_the_included_share_and_never_changes_the_total(): void
    {
        $this->createStore('vat-inclusive.example.test', [
            'vat_enabled' => true,
            'vat_rate' => 15,
            'vat_mode' => StorefrontSetting::VAT_MODE_INCLUSIVE,
            'vat_label' => 'Mushak',
        ]);
        $product = $this->createProduct('VAT Inclusive Product', 1150);

        $this->post('http://vat-inclusive.example.test/cart/items/'.$product->slug, ['quantity' => 1]);
        $this->get('http://vat-inclusive.example.test/checkout')
            ->assertOk()
            ->assertSee('data-checkout-vat-included', false)
            ->assertSee('Includes Mushak (15%)')
            ->assertSee('BDT 150');

        $order = $this->checkout('vat-inclusive.example.test', $product, 0);

        $this->assertSame(0.0, (float) $order->vat);
        $this->assertSame(1220.0, (float) $order->total_amount);
    }

    public function test_each_products_own_vat_rate_wins_when_enabled(): void
    {
        $this->createStore('vat-product-rate.example.test', [
            'vat_enabled' => true,
            'vat_rate' => 5,
            'vat_use_product_rate' => true,
        ]);
        $taxed = $this->createProduct('Own Rate Product', 1000, vatRate: 10);
        $plain = $this->createProduct('Store Rate Product', 500);

        $this->post('http://vat-product-rate.example.test/cart/items/'.$plain->slug, ['quantity' => 1]);
        $order = $this->checkout('vat-product-rate.example.test', $taxed, 1);

        $this->assertSame(125.0, (float) $order->vat); // 10% of 1000 + 5% of 500
        $this->assertSame(1695.0, (float) $order->total_amount); // 1500 + 125 + 70
    }

    public function test_offer_checkout_applies_exclusive_vat(): void
    {
        $company = $this->createStore('vat-offer.example.test', [
            'vat_enabled' => true,
            'vat_rate' => 10,
            'cod_enabled' => true,
        ]);
        $product = $this->createProduct('Offer VAT Product', 400);
        $offer = Offer::query()->create([
            'company_id' => $company->getKey(),
            'type' => Offer::TYPE_COMBO,
            'title' => 'VAT Combo',
            'status' => Offer::STATUS_PUBLISHED,
            'price_mode' => Offer::PRICE_MODE_AUTO_SUM,
        ]);
        OfferItem::query()->create([
            'company_id' => $company->getKey(),
            'offer_id' => $offer->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        $this->get('http://vat-offer.example.test/offers/'.$offer->slug)
            ->assertOk()
            ->assertSee('data-offer-vat', false)
            ->assertSee('vatPerBundle: 40', false);

        $this->post('http://vat-offer.example.test/offers/'.$offer->slug.'/checkout', [
            'name' => 'Offer VAT Buyer',
            'phone' => '01711000009',
            'address' => 'Mirpur, Dhaka',
            'quantity' => 2,
            'payment_method' => 'cod',
        ])->assertRedirectContains('/offers/'.$offer->slug.'/thank-you/');

        $order = Order::withoutGlobalScopes()->where('source', Order::SOURCE_OFFER)->latest()->firstOrFail();
        $this->assertSame(80.0, (float) $order->vat);
    }

    public function test_admin_delivery_times_show_on_product_and_checkout_pages(): void
    {
        $this->createStore('delivery-time.example.test', [
            'delivery_time_inside' => '1-2 working days',
            'delivery_time_outside' => '3-5 working days',
        ]);
        $product = $this->createProduct('Delivery Time Product', 500);

        $this->get('http://delivery-time.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-product-delivery-info', false)
            ->assertSee('1-2 working days')
            ->assertSee('3-5 working days');

        $this->post('http://delivery-time.example.test/cart/items/'.$product->slug, ['quantity' => 1]);
        $this->get('http://delivery-time.example.test/checkout')
            ->assertOk()
            ->assertSee('data-checkout-delivery-time', false)
            ->assertSee('1-2 working days')
            ->assertSee('3-5 working days');
    }

    public function test_delivery_time_is_hidden_when_not_configured(): void
    {
        $this->createStore('no-delivery-time.example.test');
        $product = $this->createProduct('No Delivery Time Product', 500);

        $this->get('http://no-delivery-time.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertDontSee('data-product-delivery-info', false);
    }

    private function checkout(string $domain, Product $product, int $quantity): Order
    {
        if ($quantity > 0) {
            $this->post("http://{$domain}/cart/items/{$product->slug}", ['quantity' => $quantity]);
        }

        $this->post("http://{$domain}/checkout", [
            'name' => 'VAT Buyer',
            'phone' => '01700111333',
            'address' => 'Mirpur, Dhaka',
        ])->assertRedirect();

        return Order::withoutGlobalScopes()->where('source', Order::SOURCE_STOREFRONT)->latest('id')->firstOrFail();
    }

    private function createProduct(string $name, float $price, float $vatRate = 0): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => str($name)->slug()->upper()->toString(),
            'price' => $price,
            'sale_price' => $price,
            'cost_price' => $price * 0.5,
            'stock' => 20,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => $vatRate,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    private function createStore(string $domain, array $settings = []): Company
    {
        static $counter = 0;
        $counter++;

        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'VAT'.$counter,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'new_customer_delivery_advance_enabled' => false,
            ...$settings,
        ]);

        app(CompanyContext::class)->set($company);

        return $company->fresh();
    }
}
