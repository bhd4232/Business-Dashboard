<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ResellerCommission;
use App\Models\ResellerProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\OrderStatusWorkflowService;
use App\Services\Reseller\ResellerCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ResellerCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $reseller;

    private Customer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Reseller Commission Co',
            'slug' => 'reseller-commission-co',
            'invoice_prefix' => 'RCC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);
        $this->user = User::factory()->create(['role' => 'super_admin']);
        Auth::login($this->user);

        $this->reseller = Customer::query()->create([
            'name' => 'Reseller One',
            'phone' => '01810000001',
            'reseller_status' => 'approved',
        ]);
        $this->buyer = Customer::query()->create(['name' => 'Test Buyer', 'phone' => '01899999999']);
        $this->product = Product::query()->create([
            'name' => 'Widget', 'sku' => 'WID-1', 'price' => 500, 'sale_price' => 500, 'stock' => 0,
        ]);
        StockMovement::query()->create(['product_id' => $this->product->id, 'type' => 'opening', 'quantity' => 100]);
        ResellerProduct::query()->create([
            'customer_id' => $this->reseller->id, 'product_id' => $this->product->id,
            'is_active' => true, 'wholesale_rate' => 350,
        ]);
    }

    public function test_commission_created_on_delivery_with_holding_status(): void
    {
        $order = $this->deliverOrder(2, 500);

        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();

        $this->assertNotNull($commission);
        $this->assertSame('holding', $commission->status);
        $this->assertEquals(300.00, $commission->gross_margin_amount); // (500-350)*2
        $this->assertEquals(0, $commission->other_costs_amount);
        $this->assertEquals(300.00, $commission->commission_amount);
        $this->assertSame(now()->addDays(3)->toDateString(), $commission->holding_until->toDateString());
    }

    public function test_no_commission_for_a_non_reseller_order(): void
    {
        $order = Order::query()->create(['customer_id' => $this->buyer->id, 'paid_amount' => 0, 'status' => Order::STATUS_DRAFT]);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 500]);

        $this->walkToDelivered($order);

        $this->assertSame(0, ResellerCommission::query()->where('order_id', $order->id)->count());
    }

    public function test_a_redelivery_never_creates_a_second_commission(): void
    {
        $order = $this->deliverOrder(1, 500);
        $this->assertSame(1, ResellerCommission::query()->where('order_id', $order->id)->count());

        // Force the observer's guard through a direct re-save of the same status pair.
        app(ResellerCommissionService::class)->createForDeliveredOrder($order->fresh());

        $this->assertSame(1, ResellerCommission::query()->where('order_id', $order->id)->count());
    }

    public function test_items_without_a_wholesale_rate_contribute_nothing(): void
    {
        $unratedProduct = Product::query()->create([
            'name' => 'Unrated Widget', 'sku' => 'WID-2', 'price' => 200, 'sale_price' => 200, 'stock' => 0,
        ]);
        StockMovement::query()->create(['product_id' => $unratedProduct->id, 'type' => 'opening', 'quantity' => 100]);
        // Picked for the store but no wholesale_rate set yet.
        ResellerProduct::query()->create(['customer_id' => $this->reseller->id, 'product_id' => $unratedProduct->id, 'is_active' => true]);

        $order = Order::query()->create(['reseller_customer_id' => $this->reseller->id, 'customer_id' => $this->buyer->id, 'paid_amount' => 0, 'status' => Order::STATUS_DRAFT]);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 500]);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $unratedProduct->id, 'quantity' => 3, 'unit_price' => 200]);
        $order = $this->walkToDelivered($order);

        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();

        $this->assertEquals(300.00, $commission->gross_margin_amount); // only the rated product's (500-350)*2
    }

    public function test_commission_is_not_promoted_before_the_hold_period_elapses(): void
    {
        $order = $this->deliverOrder(1, 500);
        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();

        $promoted = app(ResellerCommissionService::class)->promoteDueToPayable($this->company);

        $this->assertSame(0, $promoted);
        $this->assertSame('holding', $commission->fresh()->status);
    }

    public function test_commission_is_promoted_once_the_hold_period_elapses(): void
    {
        $order = $this->deliverOrder(1, 500);
        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();
        $commission->update(['holding_until' => now()->subDay()->toDateString()]);

        $promoted = app(ResellerCommissionService::class)->promoteDueToPayable($this->company);

        $this->assertSame(1, $promoted);
        $this->assertSame('payable', $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->promoted_to_payable_at);
    }

    public function test_commission_is_reversed_when_the_order_is_returned_within_the_hold_window(): void
    {
        $order = $this->deliverOrder(1, 500);

        app(OrderStatusWorkflowService::class)->transition($order, Order::STAGE_RETURNED, 'Customer returned the item.');

        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();
        $this->assertSame('reversed', $commission->status);
        $this->assertNotNull($commission->reversed_at);
    }

    public function test_a_commission_already_paid_is_left_untouched_by_a_late_return(): void
    {
        $order = $this->deliverOrder(1, 500);
        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();
        $commission->update(['status' => 'paid']);

        app(OrderStatusWorkflowService::class)->transition($order, Order::STAGE_RETURNED, 'Late return after payout.');

        $this->assertSame('paid', $commission->fresh()->status);
    }

    public function test_reseller_edit_and_view_pages_render_the_new_payout_and_commission_sections(): void
    {
        $order = $this->deliverOrder(1, 500);
        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();
        $commission->update(['status' => 'payable', 'holding_until' => now()->subDay()->toDateString()]);
        $this->company->update(['reseller_module_enabled' => true]);

        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/admin/resellers/resellers/{$this->reseller->id}/edit")
            ->assertOk()
            ->assertSee('Commission Payout')
            ->assertSee('Payout Method');

        $this->get("/admin/resellers/resellers/{$this->reseller->id}")
            ->assertOk()
            ->assertSee('Store Products')
            ->assertSee('Commissions');
    }

    public function test_cost_breakdown_reduces_the_commission_amount(): void
    {
        $order = $this->deliverOrder(2, 500); // gross margin 300
        $commission = ResellerCommission::query()->where('order_id', $order->id)->first();

        app(ResellerCommissionService::class)->updateCostBreakdown($commission, [
            ['label' => 'Courier', 'amount' => 50],
            ['label' => 'Packaging', 'amount' => 20],
        ]);

        $commission->refresh();
        $this->assertEquals(70, $commission->other_costs_amount);
        $this->assertEquals(230, $commission->commission_amount);
    }

    private function deliverOrder(int $quantity, float $unitPrice): Order
    {
        $order = Order::query()->create([
            'reseller_customer_id' => $this->reseller->id,
            'customer_id' => $this->buyer->id, 'paid_amount' => 0,
            'status' => Order::STATUS_DRAFT,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $this->product->id,
            'quantity' => $quantity, 'unit_price' => $unitPrice,
        ]);

        return $this->walkToDelivered($order);
    }

    private function walkToDelivered(Order $order): Order
    {
        $workflow = app(OrderStatusWorkflowService::class);
        $order = $workflow->transition($order, Order::STAGE_CONFIRMED);
        $order = $workflow->transition($order, Order::STAGE_PROCESSING);

        return $workflow->transition($order, Order::STAGE_DELIVERED);
    }
}
