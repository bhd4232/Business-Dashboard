<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Form-layer (Livewire) coverage for the Order create screen (audit L-5).
 * Exercises the actual Filament schema + items repeater rather than creating
 * records directly, so a repeater field that submits a bad value for a
 * NOT NULL column (the class of bug that hit Purchase) is caught here.
 */
class OrderFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_form_saves_items(): void
    {
        $company = Company::query()->create([
            'name' => 'Order Form Co',
            'slug' => 'order-form-co',
            'invoice_prefix' => 'OFC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Form Buyer',
            'phone' => '01766666666',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Order Form Product',
            'sku' => 'OFC-PROD-001',
            'price' => 100,
            'sale_price' => 100,
            'cost_price' => 60,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'status' => 'draft',
                'delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = OrderItem::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame(2, $item->quantity);
        $this->assertSame('100.00', $item->unit_price);
        $this->assertSame('200.00', $item->subtotal);
        $this->assertSame('60.00', $item->unit_cost);

        $this->assertSame('200.00', $item->order->subtotal);
    }

    /**
     * Regression test for a real production bug: paid_amount/due_amount on
     * the edit form are readOnly (see OrderForm) but that alone doesn't stop
     * them being submitted — before dehydrated(false) was added, saving the
     * edit form after adding a payment via Payments History (which recomputes
     * Order::paid_amount/due_amount straight in the database) would silently
     * overwrite the ledger-correct values with whatever was on the form when
     * the page first loaded, undoing the payment from the customer's and the
     * invoice's point of view.
     */
    public function test_saving_the_edit_form_does_not_revert_a_payment_added_via_the_ledger(): void
    {
        $company = Company::query()->create([
            'name' => 'Payment Sync Co',
            'slug' => 'payment-sync-co',
            'invoice_prefix' => 'PSC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Payment Sync Buyer',
            'phone' => '01755555555',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Payment Sync Product',
            'sku' => 'PSC-PROD-001',
            'price' => 100,
            'sale_price' => 100,
            'cost_price' => 60,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'status' => 'draft',
                'delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
                'paid_amount' => 0,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('0.00', $order->paid_amount);

        // Mounting the edit form fills paid_amount/due_amount from the order
        // as it is right now (0 / 200) -- this in-memory copy goes stale the
        // moment a payment is recorded below, which is exactly the scenario
        // the fix needs to survive.
        $editForm = Livewire::test(EditOrder::class, ['record' => $order->getKey()]);

        // Simulate what PaymentsRelationManager's own CreateAction does: add
        // a ledger row, which recomputes the order's paid_amount/due_amount
        // directly in the database (OrderPayment::booted() -> Order::
        // recalculatePaidAmount()).
        $order->payments()->create([
            'company_id' => $company->id,
            'type' => OrderPayment::TYPE_PARTIAL,
            'method' => 'cash',
            'amount' => 150,
            'paid_at' => now()->toDateString(),
        ]);

        $this->assertSame('150.00', $order->fresh()->paid_amount);

        // Saving the still-open edit form (its in-memory paid_amount/
        // due_amount are still the pre-payment 0 / 200) must not undo the
        // payment just recorded above.
        $editForm->call('save')->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame('150.00', $order->paid_amount);
        $this->assertSame('50.00', $order->due_amount);
    }
}
