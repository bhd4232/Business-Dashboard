<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
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
}
