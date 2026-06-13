<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_form_stock_creates_opening_stock_movement_for_new_stock(): void
    {
        $product = Product::query()->create([
            'name' => 'Editable Stock Product',
            'sku' => 'EDIT-STOCK-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $product->setStockFromProductForm(7);

        $this->assertSame(7, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 7,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
        ]);
    }

    public function test_product_form_stock_edit_creates_adjustment_movement(): void
    {
        $product = Product::query()->create([
            'name' => 'Adjusted Stock Product',
            'sku' => 'EDIT-STOCK-002',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 10,
        ]);

        $product->setStockFromProductForm(6);

        $this->assertSame(6, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -4,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'reason' => 'Product form stock correction',
        ]);
    }
}
