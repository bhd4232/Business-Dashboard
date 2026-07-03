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
