<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferItem;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\OfferPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OfferPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OfferPricingService::class);
    }

    public function test_auto_sum_components_subtotal_and_final_price(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 300, 'stock' => 10, 'qty' => 1],
            ['price' => 200, 'stock' => 5, 'qty' => 1],
        ]);

        $this->assertSame(500.0, $this->service->componentsSubtotal($offer));
        $this->assertSame(500.0, $this->service->finalPrice($offer));
    }

    public function test_percent_discount_reduces_final_price(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 300, 'stock' => 10, 'qty' => 1],
            ['price' => 200, 'stock' => 5, 'qty' => 1],
        ], [
            'discount_type' => Offer::DISCOUNT_PERCENT,
            'discount_value' => 10,
        ]);

        $this->assertSame(450.0, $this->service->finalPrice($offer));
    }

    public function test_flat_discount_reduces_final_price_and_never_goes_below_zero(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 100, 'stock' => 10, 'qty' => 1],
        ], [
            'discount_type' => Offer::DISCOUNT_FLAT,
            'discount_value' => 500,
        ]);

        $this->assertSame(0.0, $this->service->finalPrice($offer));
    }

    public function test_manual_price_mode_ignores_component_subtotal_as_base(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 300, 'stock' => 10, 'qty' => 1],
            ['price' => 200, 'stock' => 5, 'qty' => 1],
        ], [
            'price_mode' => Offer::PRICE_MODE_MANUAL,
            'manual_price' => 399,
        ]);

        $this->assertSame(500.0, $this->service->componentsSubtotal($offer));
        $this->assertSame(399.0, $this->service->finalPrice($offer));
    }

    public function test_explode_to_order_lines_distributes_quantity_and_price_proportionally(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 300, 'stock' => 10, 'qty' => 1],
            ['price' => 200, 'stock' => 5, 'qty' => 2],
        ], [
            'discount_type' => Offer::DISCOUNT_PERCENT,
            'discount_value' => 10,
        ]);

        $lines = $this->service->explodeToOrderLines($offer, 3);

        $this->assertCount(2, $lines);
        // component subtotal = 300*1 + 200*2 = 700; final price = 630 (10% off)
        $this->assertSame(3, $lines[0]['quantity']); // 1 * 3 ordered
        $this->assertSame(6, $lines[1]['quantity']); // 2 * 3 ordered

        $totalRevenue = $lines[0]['unit_price'] * $lines[0]['quantity'] + $lines[1]['unit_price'] * $lines[1]['quantity'];
        $this->assertEqualsWithDelta(630.0 * 3, $totalRevenue, 0.5);
    }

    public function test_assert_in_stock_throws_when_a_component_is_short(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 300, 'stock' => 1, 'qty' => 1],
        ]);

        try {
            $this->service->assertInStock($offer, 2);
            $this->fail('Expected a 422 HTTP exception for insufficient stock.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_assert_in_stock_passes_when_enough_stock(): void
    {
        $company = $this->createCompany();
        $offer = $this->createCombo($company, [
            ['price' => 300, 'stock' => 10, 'qty' => 2],
        ]);

        $this->service->assertInStock($offer, 3);
        $this->addToAssertionCount(1);
    }

    protected function createCompany(): Company
    {
        static $counter = 0;
        $counter++;

        $company = Company::query()->create([
            'name' => 'Offer Pricing Co '.$counter,
            'slug' => 'offer-pricing-co-'.$counter,
            'invoice_prefix' => 'OPC'.$counter,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($company);

        return $company;
    }

    /**
     * @param  array<int, array{price:float,stock:int,qty:int}>  $components
     */
    protected function createCombo(Company $company, array $components, array $offerOverrides = []): Offer
    {
        $offer = Offer::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'type' => Offer::TYPE_COMBO,
            'title' => 'Combo '.uniqid(),
            'status' => Offer::STATUS_PUBLISHED,
            'price_mode' => Offer::PRICE_MODE_AUTO_SUM,
        ], $offerOverrides));

        foreach ($components as $index => $component) {
            $product = Product::query()->create([
                'company_id' => $company->getKey(),
                'name' => 'Component '.$index.' '.uniqid(),
                'sku' => 'COMP-'.uniqid(),
                'price' => $component['price'],
                'sale_price' => $component['price'],
                'cost_price' => $component['price'] * 0.6,
                'stock' => $component['stock'],
                'unit' => 'pcs',
                'reorder_level' => 1,
                'vat_rate' => 0,
                'is_active' => true,
                'status' => Product::STATUS_AVAILABLE,
            ]);

            OfferItem::query()->create([
                'company_id' => $company->getKey(),
                'offer_id' => $offer->getKey(),
                'product_id' => $product->getKey(),
                'quantity' => $component['qty'],
                'sort_order' => $index,
            ]);
        }

        return $offer->fresh(['items.product', 'items.productVariant']);
    }
}
