<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteApiProductTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected string $token;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Tasneem Knitting',
            'slug' => 'tasneem-knitting',
            'invoice_prefix' => 'TK',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        [, $this->token] = CompanyApiKey::generate($this->company);

        app(CompanyContext::class)->set($this->company);
        $this->product = Product::query()->create([
            'name' => 'Cotton Yarn',
            'sku' => 'YARN-001',
            'price' => 500,
            'sale_price' => 480,
            'cost_price' => 400,
            'stock' => 0,
            'unit' => 'kg',
            'reorder_level' => 5,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        $this->product->setStockFromProductForm(100);
        app(CompanyContext::class)->clear();
    }

    protected function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_it_lists_active_products(): void
    {
        $this->getJson('/api/v1/products', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'YARN-001')
            ->assertJsonPath('data.0.stock', 100);
    }

    public function test_it_shows_a_single_product_by_sku(): void
    {
        $this->getJson('/api/v1/products/YARN-001', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.name', 'Cotton Yarn')
            ->assertJsonPath('data.price', 500);
    }

    public function test_updating_price_writes_the_field_directly(): void
    {
        $this->patchJson('/api/v1/products/YARN-001', ['price' => 550], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.price', 550);

        $this->assertEquals(550, $this->product->refresh()->price);
    }

    /**
     * The core "compatible with StockMovement" requirement: a stock update
     * from the website must never overwrite Product.stock directly — it has
     * to land as an auditable ledger entry, exactly like an admin editing
     * stock from the Product form does.
     */
    public function test_updating_stock_creates_a_stock_movement_instead_of_overwriting_the_column(): void
    {
        $this->patchJson('/api/v1/products/YARN-001', ['stock' => 130], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.stock', 130);

        $this->assertEquals(130, $this->product->refresh()->stock);

        $movement = StockMovement::withoutGlobalScopes()
            ->where('product_id', $this->product->getKey())
            ->where('type', 'adjustment')
            ->latest('id')
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(30, $movement->quantity);
        $this->assertSame(CompanyApiKey::class, $movement->reference_type);
    }

    public function test_at_least_one_field_is_required(): void
    {
        $this->patchJson('/api/v1/products/YARN-001', [], $this->auth())
            ->assertStatus(422);
    }

    public function test_unknown_sku_returns_not_found(): void
    {
        $this->getJson('/api/v1/products/'.Str::random(10), $this->auth())
            ->assertStatus(404);
    }
}
