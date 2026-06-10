<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_purchase_updates_totals_stock_and_supplier_balance(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'opening_balance' => 100,
        ]);

        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'PUR-001',
            'price' => 150,
            'sale_price' => 150,
            'stock' => 0,
        ]);

        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'discount' => 10,
            'vat' => 5,
            'freight_to_ctg' => 20,
            'duty' => 30,
            'custom_costs' => [
                ['label' => 'Warehouse Charge', 'amount' => 40],
            ],
            'paid_amount' => 20,
            'status' => 'received',
            'update_cost_price' => true,
        ]);

        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_cost' => 50,
        ]);

        $this->assertSame('150.00', $purchase->refresh()->subtotal);
        $this->assertSame('235.00', $purchase->total_amount);
        $this->assertSame('215.00', $purchase->due_amount);
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertSame('50.00', $product->cost_price);
        $this->assertSame('315.00', $supplier->refresh()->current_balance);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'purchase',
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'quantity' => 3,
        ]);
    }

    public function test_coming_soon_purchase_products_can_be_ensured(): void
    {
        Product::ensureComingSoonPurchaseProducts();

        $this->assertDatabaseHas('products', [
            'name' => 'Machine Purchase',
            'status' => Product::STATUS_COMING_SOON,
            'is_active' => false,
        ]);

        $this->assertSame(count(Product::COMING_SOON_PURCHASE_PRODUCTS), Product::query()
            ->where('status', Product::STATUS_COMING_SOON)
            ->count());
    }

    public function test_draft_purchase_does_not_increase_stock_until_received(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test Supplier']);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'PUR-002',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
        ]);

        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_cost' => 25,
        ]);

        $this->assertSame(0, $product->refresh()->stock);
        $this->assertSame(0, StockMovement::query()->count());

        $purchase->update(['status' => 'received']);

        $this->assertSame(4, $product->refresh()->stock);
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_cancelling_received_purchase_removes_stock_movement(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test Supplier']);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'PUR-003',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 30,
        ]);

        $this->assertSame(2, $product->refresh()->stock);

        $purchase->update(['status' => 'cancelled']);

        $this->assertSame(0, $product->refresh()->stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_received_purchase_cannot_be_cancelled_when_stock_would_be_negative(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Test Supplier']);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'PUR-004',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 30,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $purchase->update(['status' => 'cancelled']);
    }
}
