<?php

namespace Tests\Feature;

use App\Models\CourierBooking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_multi_product_order_updates_totals_stock_and_customer_balance(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'opening_balance' => 50,
        ]);

        $firstProduct = Product::query()->create([
            'name' => 'First Product',
            'sku' => 'SALE-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $secondProduct = Product::query()->create([
            'name' => 'Second Product',
            'sku' => 'SALE-002',
            'price' => 80,
            'sale_price' => 80,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $firstProduct->id,
            'type' => 'opening',
            'quantity' => 10,
        ]);
        StockMovement::query()->create([
            'product_id' => $secondProduct->id,
            'type' => 'opening',
            'quantity' => 10,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'discount' => 10,
            'vat' => 5,
            'paid_amount' => 100,
            'status' => 'completed',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'quantity' => 1,
            'unit_price' => 80,
        ]);

        $this->assertSame('280.00', $order->refresh()->subtotal);
        $this->assertSame('275.00', $order->total_amount);
        $this->assertSame('175.00', $order->due_amount);
        $this->assertSame(8, $firstProduct->refresh()->stock);
        $this->assertSame(9, $secondProduct->refresh()->stock);
        $this->assertSame('225.00', $customer->refresh()->current_balance);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $firstProduct->id,
            'type' => 'sale',
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'quantity' => 2,
        ]);
    }

    public function test_draft_order_does_not_decrease_stock_until_confirmed(): void
    {
        $customer = Customer::query()->create(['name' => 'Test Customer']);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'SALE-003',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 5,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
        ]);

        $this->assertSame(5, $product->refresh()->stock);

        $order->update(['status' => 'confirmed']);

        $this->assertSame(2, $product->refresh()->stock);
    }

    public function test_confirmed_order_blocks_insufficient_stock(): void
    {
        $customer = Customer::query()->create(['name' => 'Test Customer']);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'sku' => 'SALE-004',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 2,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
        ]);

        $this->expectException(ValidationException::class);

        $order->update(['status' => 'confirmed']);
    }

    public function test_order_edit_form_exposes_delivery_status_for_storefront_tracking(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create(['name' => 'Tracking Admin Customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => 'draft',
            'delivery_status' => CourierBooking::STATUS_BOOKED,
        ]);

        $this->actingAs($user)
            ->get("/admin/sales/orders/{$order->getKey()}/edit")
            ->assertOk()
            ->assertSee('Order Status')
            ->assertSee('Delivery Status')
            ->assertSee('Booked')
            ->assertSee('In Transit');
    }
}
