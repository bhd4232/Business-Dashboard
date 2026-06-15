<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_blocked_when_stock_is_insufficient(): void
    {
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 5,
        ]);

        $this->expectException(ValidationException::class);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => 6,
        ]);
    }

    public function test_sale_quantity_is_normalized_and_updates_stock(): void
    {
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-002',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 5,
        ]);

        $sale = StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => -2,
        ]);

        $this->assertSame(2, $sale->refresh()->quantity);
        $this->assertSame(-2, $sale->signed_quantity);
        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_stock_movement_service_projects_stock(): void
    {
        $product = Product::query()->create([
            'name' => 'Service Product',
            'sku' => 'SERVICE-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 5,
        ]);

        $service = app(StockMovementService::class);

        $this->assertSame(3, $service->projectedStockFor($product->id, 'sale', 2));
        $this->assertSame(-2, $service->signedQuantityFor('sale', 2));
        $this->assertSame(2, $service->normalizeQuantity('sale', -2));
    }

    public function test_adjustment_keeps_signed_quantity_and_cannot_make_stock_negative(): void
    {
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-003',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 5,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -3,
            'reason' => 'Damaged stock removal',
        ]);

        $this->assertSame(2, $product->refresh()->stock);

        $this->expectException(ValidationException::class);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -3,
            'reason' => 'Second damaged stock removal',
        ]);
    }

    public function test_adjustment_requires_reason(): void
    {
        $product = Product::query()->create([
            'name' => 'Adjustment Reason Product',
            'sku' => 'TEST-004',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $this->expectException(ValidationException::class);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 3,
        ]);
    }
}
