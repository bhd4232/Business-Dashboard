<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\Order;
use App\Models\Product;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteApiOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected string $token;

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
        Product::query()->create([
            'name' => 'Cotton Yarn', 'sku' => 'YARN-001', 'price' => 500,
            'sale_price' => 480, 'cost_price' => 400, 'stock' => 100, 'unit' => 'kg',
            'reorder_level' => 5, 'vat_rate' => 0, 'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        app(CompanyContext::class)->clear();
    }

    protected function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    protected function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'external_reference' => 'WEB-1001',
            'customer' => [
                'name' => 'Rahim Uddin',
                'phone' => '01711223344',
                'address' => 'Dhaka, Bangladesh',
            ],
            'items' => [
                ['sku' => 'YARN-001', 'quantity' => 5],
            ],
        ], $overrides);
    }

    public function test_it_creates_an_order_with_the_website_api_source(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->orderPayload(), $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.status', Order::STATUS_DRAFT);

        $orderNumber = $response->json('data.order_number');

        app(CompanyContext::class)->set($this->company);
        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();

        $this->assertSame(Order::SOURCE_API, $order->source);
        $this->assertSame('webapi-WEB-1001', $order->external_reference);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_reposting_the_same_external_reference_does_not_duplicate_the_order(): void
    {
        $this->postJson('/api/v1/orders', $this->orderPayload(), $this->auth())->assertCreated();
        $this->postJson('/api/v1/orders', $this->orderPayload(), $this->auth())->assertOk();

        app(CompanyContext::class)->set($this->company);
        $this->assertSame(1, Order::query()->where('external_reference', 'webapi-WEB-1001')->count());
    }

    public function test_an_unknown_sku_is_rejected(): void
    {
        $this->postJson(
            '/api/v1/orders',
            $this->orderPayload(['items' => [['sku' => 'NOPE', 'quantity' => 1]]]),
            $this->auth(),
        )->assertStatus(422);
    }

    public function test_it_reports_order_status(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->orderPayload(), $this->auth())->assertCreated();
        $orderNumber = $response->json('data.order_number');

        $this->getJson("/api/v1/orders/{$orderNumber}", $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DRAFT);
    }
}
