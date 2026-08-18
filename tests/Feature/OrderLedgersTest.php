<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderCost;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order-level financial ledgers added to close the gap against the
 * competitor ERP's order-detail page: Payments History (OrderPayment,
 * replacing the old single paid_amount input as the source of truth after
 * creation) and Associated Costs (OrderCost, feeding Order::profit()).
 */
class OrderLedgersTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_order_with_a_paid_amount_seeds_one_advance_payment_row(): void
    {
        $order = $this->makeOrder('Ledger Seed Customer', paidAmount: 200);

        $this->assertCount(1, $order->payments);
        $payment = $order->payments->first();
        $this->assertSame(OrderPayment::TYPE_ADVANCE, $payment->type);
        $this->assertEquals(200, (float) $payment->amount);
        $this->assertEquals(200, (float) $order->paid_amount);
        $this->assertEquals(300, (float) $order->due_amount);
    }

    public function test_creating_an_order_with_no_paid_amount_seeds_no_payment_row(): void
    {
        $order = $this->makeOrder('Ledger No Payment Customer', paidAmount: 0);

        $this->assertCount(0, $order->payments);
        $this->assertEquals(0, (float) $order->paid_amount);
    }

    public function test_adding_a_payment_row_increases_paid_amount_and_decreases_due_amount(): void
    {
        $order = $this->makeOrder('Ledger Add Payment Customer', paidAmount: 0);

        $order->payments()->create([
            'type' => OrderPayment::TYPE_PARTIAL,
            'method' => 'bank',
            'amount' => 150,
            'paid_at' => now()->toDateString(),
        ]);

        $order->refresh();

        $this->assertEquals(150, (float) $order->paid_amount);
        $this->assertEquals(350, (float) $order->due_amount);
    }

    public function test_editing_a_payment_row_recomputes_paid_and_due_amount(): void
    {
        $order = $this->makeOrder('Ledger Edit Payment Customer', paidAmount: 100);
        $payment = $order->payments->first();

        $payment->update(['amount' => 250]);
        $order->refresh();

        $this->assertEquals(250, (float) $order->paid_amount);
        $this->assertEquals(250, (float) $order->due_amount);
    }

    public function test_deleting_a_payment_row_recomputes_paid_and_due_amount(): void
    {
        $order = $this->makeOrder('Ledger Delete Payment Customer', paidAmount: 100);
        $order->payments()->create([
            'type' => OrderPayment::TYPE_PARTIAL,
            'amount' => 150,
            'paid_at' => now()->toDateString(),
        ]);
        $order->refresh();
        $this->assertEquals(250, (float) $order->paid_amount);

        $order->payments()->where('type', OrderPayment::TYPE_PARTIAL)->first()->delete();
        $order->refresh();

        $this->assertEquals(100, (float) $order->paid_amount);
        $this->assertEquals(400, (float) $order->due_amount);
    }

    public function test_payment_amount_must_be_greater_than_zero(): void
    {
        $order = $this->makeOrder('Ledger Zero Payment Customer', paidAmount: 0);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $order->payments()->create([
            'type' => OrderPayment::TYPE_ADVANCE,
            'amount' => 0,
            'paid_at' => now()->toDateString(),
        ]);
    }

    public function test_associated_costs_feed_total_cost_and_profit(): void
    {
        // Product: sale_price 500, cost_price 300 -> COGS 300 for qty 1.
        $order = $this->makeOrder('Ledger Cost Customer', paidAmount: 0);
        $this->assertEquals(500, (float) $order->total_amount);
        $this->assertEquals(200, $order->profit()); // 500 - 300 COGS - 0 costs

        $order->costs()->create(['cost_head' => OrderCost::HEAD_COURIER_DELIVERY, 'amount' => 60]);
        $order->costs()->create(['cost_head' => OrderCost::HEAD_COD, 'amount' => 15]);

        $this->assertEquals(75, $order->totalCost());
        $this->assertEquals(125, $order->profit()); // 500 - 300 - 75

        $order->costs()->where('cost_head', OrderCost::HEAD_COURIER_DELIVERY)->first()->delete();
        $this->assertEquals(15, $order->totalCost());
        $this->assertEquals(185, $order->profit()); // 500 - 300 - 15
    }

    public function test_cost_amount_cannot_be_negative(): void
    {
        $order = $this->makeOrder('Ledger Negative Cost Customer', paidAmount: 0);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $order->costs()->create(['cost_head' => OrderCost::HEAD_OTHER, 'amount' => -10]);
    }

    protected function makeOrder(string $customerName, float $paidAmount = 0): Order
    {
        $customer = Customer::query()->create([
            'name' => $customerName,
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Ledger Product '.uniqid(),
            'sku' => 'LEDGER-'.uniqid(),
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->getKey(),
            'type' => 'opening',
            'quantity' => 5,
            'reference_type' => Product::class,
            'reference_id' => $product->getKey(),
            'note' => 'Ledger test stock',
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'order_date' => now()->toDateString(),
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => $paidAmount,
            'status' => 'draft',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
            'unit_price' => 500,
        ]);
        $order->update(['status' => 'completed']);

        return $order->refresh();
    }
}
