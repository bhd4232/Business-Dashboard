<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_three_admin_pages_render_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create(['name' => 'Admin Customer']);
        $product = Product::query()->create([
            'name' => 'Admin Sale Product',
            'sku' => 'ADMIN-SALE-001',
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
            'status' => 'completed',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $this->actingAs($user)
            ->get('/admin/customers')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/orders/create')
            ->assertOk();

        $this->actingAs($user)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Admin Sale Product');

        $this->actingAs($user)
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('Invoice')
            ->assertSee('Admin Sale Product');
    }
}
