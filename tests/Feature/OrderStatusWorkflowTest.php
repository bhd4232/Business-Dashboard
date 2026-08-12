<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\RelationManagers\StatusTransitionsRelationManager;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusTransition;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderStatusWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_workflow_maps_shipping_and_delivery_without_double_counting_stock(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create(['name' => 'Workflow Customer']);
        $product = Product::query()->create([
            'name' => 'Workflow Product',
            'sku' => 'WORKFLOW-001',
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
            'paid_amount' => 0,
            'status' => Order::STATUS_DRAFT,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        $provider = CourierProvider::query()->create([
            'company_id' => $order->company_id,
            'name' => 'Workflow Courier',
            'slug' => 'workflow-courier',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'credentials' => [],
            'settings' => [],
            'is_active' => true,
        ]);
        $booking = CourierBooking::query()->create([
            'company_id' => $order->company_id,
            'courier_provider_id' => $provider->id,
            'order_id' => $order->id,
            'tracking_id' => 'WORKFLOW-TRACK-1',
            'recipient_name' => $customer->name,
            'status' => CourierBooking::STATUS_BOOKED,
        ]);

        $this->actingAs($user);
        $workflow = app(OrderStatusWorkflowService::class);
        $order = $workflow->transition($order, Order::STAGE_CONFIRMED);
        $order = $workflow->transition($order, Order::STAGE_PROCESSING);
        $order = $workflow->transition($order, Order::STAGE_SHIPPING);
        $order = $workflow->transition($order, Order::STAGE_DELIVERED);

        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $order->delivery_status);
        $this->assertSame(CourierBooking::STATUS_DELIVERED, $booking->refresh()->status);
        $this->assertSame(2, $booking->statusLogs()->count());
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertSame('200.00', $customer->refresh()->current_balance);
        $this->assertSame(4, $order->statusTransitions()->count());
        $this->assertDatabaseHas('order_status_transitions', [
            'order_id' => $order->id,
            'changed_by' => $user->id,
            'stage' => Order::STAGE_SHIPPING,
            'from_status' => Order::STATUS_PROCESSING,
            'to_status' => Order::STATUS_PROCESSING,
            'from_delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
            'to_delivery_status' => CourierBooking::STATUS_IN_TRANSIT,
            'source' => 'manual',
        ]);
    }

    public function test_invalid_transition_is_rejected_without_changing_the_order(): void
    {
        $customer = Customer::query()->create(['name' => 'Invalid Transition Customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => Order::STATUS_DRAFT,
        ]);

        try {
            app(OrderStatusWorkflowService::class)->transition($order, Order::STAGE_DELIVERED);
            $this->fail('The invalid workflow transition should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stage', $exception->errors());
        }

        $this->assertSame(Order::STATUS_DRAFT, $order->refresh()->status);
        $this->assertDatabaseCount('order_status_transitions', 0);
    }

    public function test_cancellation_requires_a_reason_and_restores_stock_and_customer_due(): void
    {
        $customer = Customer::query()->create(['name' => 'Cancellation Customer']);
        $product = Product::query()->create([
            'name' => 'Cancellation Product',
            'sku' => 'WORKFLOW-002',
            'price' => 80,
            'sale_price' => 80,
            'stock' => 0,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 4,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => Order::STATUS_DRAFT,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 80,
        ]);
        $workflow = app(OrderStatusWorkflowService::class);
        $order = $workflow->transition($order, Order::STAGE_CONFIRMED);

        try {
            $workflow->transition($order, Order::STAGE_CANCELLED);
            $this->fail('Cancellation without a reason should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $order = $workflow->transition($order, Order::STAGE_CANCELLED, 'Customer requested cancellation.');

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(4, $product->refresh()->stock);
        $this->assertSame('0.00', $customer->refresh()->current_balance);
        $this->assertDatabaseHas('order_status_transitions', [
            'order_id' => $order->id,
            'stage' => Order::STAGE_CANCELLED,
            'reason' => 'Customer requested cancellation.',
        ]);
    }

    public function test_direct_status_updates_are_still_audited(): void
    {
        $customer = Customer::query()->create(['name' => 'Direct Audit Customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => Order::STATUS_DRAFT,
        ]);

        $order->update(['status' => Order::STATUS_CONFIRMED]);

        $transition = OrderStatusTransition::query()->sole();
        $this->assertSame(Order::STATUS_DRAFT, $transition->from_status);
        $this->assertSame(Order::STATUS_CONFIRMED, $transition->to_status);
        $this->assertSame('direct', $transition->source);
        $this->assertNull($transition->stage);
    }

    public function test_order_pages_render_controlled_status_ui_and_history(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create(['name' => 'Admin Workflow Customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => Order::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->get("/admin/sales/orders/{$order->getKey()}")
            ->assertOk()
            ->assertSee('Change status');

        $this->assertContains(StatusTransitionsRelationManager::class, OrderResource::getRelations());

        $this->actingAs($user)
            ->get("/admin/sales/orders/{$order->getKey()}/edit")
            ->assertOk()
            ->assertSee('Use Change status to follow the controlled workflow.');
    }
}
