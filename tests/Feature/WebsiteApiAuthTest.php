<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\Product;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCompany(string $name, string $prefix): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'invoice_prefix' => $prefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    public function test_request_without_a_token_is_rejected(): void
    {
        $this->getJson('/api/v1/products')
            ->assertStatus(401)
            ->assertJson(['error' => 'missing_api_key']);
    }

    public function test_request_with_an_invalid_token_is_rejected(): void
    {
        $this->getJson('/api/v1/products', ['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_api_key']);
    }

    public function test_a_revoked_key_is_rejected(): void
    {
        $company = $this->makeCompany('Tasneem Knitting', 'TK');
        [$key, $token] = CompanyApiKey::generate($company);
        $key->update(['revoked_at' => now()]);

        $this->getJson('/api/v1/products', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_api_key']);
    }

    public function test_a_valid_key_scopes_requests_to_its_own_company_only(): void
    {
        $companyA = $this->makeCompany('Tasneem Knitting', 'TK');
        $companyB = $this->makeCompany('Other Company', 'OC');

        [, $tokenA] = CompanyApiKey::generate($companyA);

        app(CompanyContext::class)->set($companyA);
        Product::query()->create([
            'name' => 'Company A Product', 'sku' => 'A-1', 'price' => 100,
            'sale_price' => 100, 'cost_price' => 80, 'stock' => 5, 'unit' => 'pcs',
            'reorder_level' => 1, 'vat_rate' => 0, 'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        app(CompanyContext::class)->set($companyB);
        Product::query()->create([
            'name' => 'Company B Product', 'sku' => 'B-1', 'price' => 200,
            'sale_price' => 200, 'cost_price' => 150, 'stock' => 3, 'unit' => 'pcs',
            'reorder_level' => 1, 'vat_rate' => 0, 'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        app(CompanyContext::class)->clear();

        $response = $this->getJson('/api/v1/products', ['Authorization' => "Bearer {$tokenA}"])
            ->assertOk();

        $skus = collect($response->json('data'))->pluck('sku')->all();

        $this->assertSame(['A-1'], $skus);
    }

    public function test_a_companys_key_cannot_read_another_companys_product_by_sku(): void
    {
        $companyA = $this->makeCompany('Tasneem Knitting', 'TK');
        $companyB = $this->makeCompany('Other Company', 'OC');

        [, $tokenA] = CompanyApiKey::generate($companyA);

        app(CompanyContext::class)->set($companyB);
        Product::query()->create([
            'name' => 'Company B Product', 'sku' => 'B-1', 'price' => 200,
            'sale_price' => 200, 'cost_price' => 150, 'stock' => 3, 'unit' => 'pcs',
            'reorder_level' => 1, 'vat_rate' => 0, 'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        app(CompanyContext::class)->clear();

        $this->getJson('/api/v1/products/B-1', ['Authorization' => "Bearer {$tokenA}"])
            ->assertStatus(404);
    }
}
