<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Variant Co',
            'slug' => 'variant-co',
            'invoice_prefix' => 'VAR',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);
    }

    private function makeVariableProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Variable Shirt',
            'sku' => 'VAR-SHIRT-001',
            'price' => 1000,
            'sale_price' => 900,
            'cost_price' => 500,
            'stock' => 0,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'has_variants' => true,
            'variant_attributes' => ['Size' => ['M', 'L']],
        ]);
    }

    public function test_variant_stock_syncs_to_parent_product(): void
    {
        $product = $this->makeVariableProduct();

        $m = $product->variants()->create(['options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]);
        $product->variants()->create(['options' => ['Size' => 'L'], 'stock' => 7, 'is_active' => true]);

        $this->assertSame(12, (int) $product->fresh()->stock);

        $m->update(['stock' => 2]);
        $this->assertSame(9, (int) $product->fresh()->stock);

        $m->delete();
        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_inactive_variants_excluded_from_stock_sum(): void
    {
        $product = $this->makeVariableProduct();

        $product->variants()->create(['options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]);
        $product->variants()->create(['options' => ['Size' => 'L'], 'stock' => 7, 'is_active' => false]);

        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_variant_label_and_price_fallback(): void
    {
        $product = $this->makeVariableProduct();

        $variant = $product->variants()->create([
            'options' => ['Size' => 'M', 'Color' => 'Red'],
            'stock' => 1,
        ]);

        $this->assertSame('Size: M / Color: Red', $variant->label());
        $this->assertSame(900.0, $variant->effectiveSalePrice());

        $variant->update(['sale_price' => 750]);
        $this->assertSame(750.0, $variant->fresh()->effectiveSalePrice());
    }

    public function test_confirmed_order_deducts_variant_stock_and_restores_on_cancel(): void
    {
        $product = $this->makeVariableProduct();
        $variant = $product->variants()->create(['options' => ['Size' => 'M'], 'stock' => 10, 'is_active' => true]);

        $customer = \App\Models\Customer::query()->create([
            'name' => 'Variant Buyer',
            'phone' => '01700000001',
            'customer_type' => 'regular',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $order = \App\Models\Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'order_date' => now()->toDateString(),
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
            'status' => 'draft',
        ]);

        \App\Models\OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'product_variant_id' => $variant->getKey(),
            'variant_label' => $variant->label(),
            'quantity' => 3,
            'unit_price' => 900,
        ]);

        // Draft: no stock deduction yet.
        $this->assertSame(10, (int) $variant->fresh()->stock);

        $order->refresh()->update(['status' => 'confirmed']);
        $this->assertSame(7, (int) $variant->fresh()->stock);
        $this->assertSame(7, (int) $product->fresh()->stock);

        $order->refresh()->update(['status' => 'cancelled']);
        $this->assertSame(10, (int) $variant->fresh()->stock);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_multiple_variants_added_to_cart_in_one_submit(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->company->update(['domain' => 'variants.example.test', 'domain_verified' => true]);

        \App\Models\StorefrontSetting::query()->create([
            'company_id' => $this->company->getKey(),
            'theme_color' => '#0F766E',
            'meta_title' => 'Variant Co',
            'is_published' => true,
        ]);

        $product = $this->makeVariableProduct();
        $m = $product->variants()->create(['options' => ['Size' => 'M'], 'stock' => 5, 'is_active' => true]);
        $l = $product->variants()->create(['options' => ['Size' => 'L'], 'stock' => 5, 'sale_price' => 950, 'is_active' => true]);

        $this->post('http://variants.example.test/cart/items/'.$product->slug, [
            'quantities' => [
                $m->getKey() => 2,
                $l->getKey() => 3,
            ],
        ])->assertRedirect();

        $this->get('http://variants.example.test/cart')
            ->assertOk()
            ->assertSee('Size: M')
            ->assertSee('Size: L')
            ->assertSee('BDT 900.00')
            ->assertSee('BDT 950.00');
    }

    public function test_variant_gets_company_id_from_product(): void
    {
        $product = $this->makeVariableProduct();

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'options' => ['Size' => 'M'],
            'stock' => 1,
        ]);

        $this->assertSame($this->company->getKey(), (int) $variant->fresh()->company_id);
    }
}
