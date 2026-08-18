<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Print invoices" bulk action on the Orders list: select multiple orders,
 * one click opens every selected invoice in a single new tab and triggers
 * the browser/OS print dialog — no per-order clicking needed.
 */
class OrderBulkPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_print_route_renders_every_selected_orders_invoice_and_auto_triggers_print(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $orderOne = $this->makeOrder('Bulk Print One');
        $orderTwo = $this->makeOrder('Bulk Print Two');

        $response = $this->actingAs($admin)
            ->get(route('orders.print.bulk', [
                'orders' => "{$orderOne->getKey()},{$orderTwo->getKey()}",
            ]))
            ->assertOk()
            ->assertSee($orderOne->order_number)
            ->assertSee($orderTwo->order_number)
            // Two separate .invoice-page wrappers, one per order.
            ->assertSeeInOrder(['invoice-page', 'invoice-page'], false);

        $response->assertSee('window.print()', false);
    }

    public function test_bulk_print_route_only_includes_orders_belonging_to_the_current_company(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Bulk Print Co A', 'slug' => 'bulk-print-co-a',
            'invoice_prefix' => 'BPA', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        $companyB = Company::query()->create([
            'name' => 'Bulk Print Co B', 'slug' => 'bulk-print-co-b',
            'invoice_prefix' => 'BPB', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);

        app(CompanyContext::class)->set($companyA);
        $orderA = $this->makeOrder('Company A Customer');

        app(CompanyContext::class)->set($companyB);
        $orderB = $this->makeOrder('Company B Customer');

        $user = User::factory()->create(['role' => 'sales_staff', 'is_active' => true]);
        $user->companies()->sync([$companyA->getKey() => ['role' => 'sales_staff', 'is_default' => true]]);

        $this->actingAs($user)
            ->get(route('orders.print.bulk', [
                'orders' => "{$orderA->getKey()},{$orderB->getKey()}",
            ]))
            ->assertOk()
            ->assertSee($orderA->order_number)
            ->assertDontSee($orderB->order_number)
            ->assertDontSee('Company B Customer');
    }

    public function test_bulk_print_route_404s_when_no_valid_order_ids_are_given(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('orders.print.bulk', ['orders' => 'not-a-number,also-not']))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('orders.print.bulk'))
            ->assertNotFound();
    }

    public function test_orders_list_print_invoices_bulk_action_opens_the_bulk_print_route_for_the_selected_orders_in_a_new_tab(): void
    {
        $user = User::factory()->create(['role' => 'sales_staff', 'is_active' => true]);
        $company = Company::defaultCompany();
        $user->companies()->sync([$company->getKey() => ['role' => 'sales_staff', 'is_default' => true]]);
        app(CompanyContext::class)->set($company);

        $orderOne = $this->makeOrder('Print Action One');
        $orderTwo = $this->makeOrder('Print Action Two');

        $this->actingAs($user);

        // Runs end-to-end through the real Livewire bulk-action lifecycle
        // (same mechanism bookCourierBulk already uses), so $records
        // inside the action reflects this exact selection — confirms the
        // action is wired into the table and executes without error for a
        // real selection.
        Livewire::test(ListOrders::class)
            ->assertTableBulkActionExists('printInvoicesBulk')
            ->assertTableBulkActionHasLabel('printInvoicesBulk', 'Print invoices')
            ->assertTableBulkActionHasIcon('printInvoicesBulk', 'heroicon-o-printer')
            ->mountTableBulkAction('printInvoicesBulk', [$orderOne, $orderTwo])
            ->callMountedTableBulkAction();

        // What URL it opens is covered directly against
        // OrdersTable::printInvoicesBulk() here, since asserting on a
        // Livewire "js()" effect payload isn't part of the public testing
        // API — a $livewire double simply records what would have been
        // sent to the browser.
        $livewireSpy = new class
        {
            /** @var array<int, string> */
            public array $jsCalls = [];

            public function js(string $expression, mixed ...$params): void
            {
                $this->jsCalls[] = $expression;
            }
        };

        $reflection = new \ReflectionMethod(\App\Filament\Resources\Orders\Tables\OrdersTable::class, 'printInvoicesBulk');
        $reflection->invoke(null, collect([$orderOne, $orderTwo]), $livewireSpy);

        $expectedUrl = route('orders.print.bulk', ['orders' => "{$orderOne->getKey()},{$orderTwo->getKey()}"]);

        $this->assertCount(1, $livewireSpy->jsCalls);
        // The URL is embedded via json_encode() (forward slashes escaped as
        // `\/`, which is valid — and equivalent to `/` — inside a JS string
        // literal too), so compare against the same encoding rather than
        // the raw URL.
        $this->assertStringContainsString(json_encode($expectedUrl), $livewireSpy->jsCalls[0]);

        // Nothing selected → no-op, doesn't try to open a URL with an
        // empty order list.
        $emptySpy = new class
        {
            /** @var array<int, string> */
            public array $jsCalls = [];

            public function js(string $expression, mixed ...$params): void
            {
                $this->jsCalls[] = $expression;
            }
        };
        $reflection->invoke(null, collect(), $emptySpy);
        $this->assertCount(0, $emptySpy->jsCalls);
    }

    protected function makeOrder(string $customerName): Order
    {
        $customer = Customer::query()->create([
            'name' => $customerName,
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Bulk Print Product '.uniqid(),
            'sku' => 'BULK-PRINT-'.uniqid(),
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
            'note' => 'Bulk print test stock',
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'order_date' => now()->toDateString(),
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
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
