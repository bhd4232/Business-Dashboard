<?php

namespace Tests\Feature;

use App\Filament\Widgets\BusinessOverview;
use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TransactionLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_business_overview_stat_except_account_balance_opens_a_drilldown_modal(): void
    {
        $user = User::factory()->create();
        $this->seedDrilldownData();

        $actionsAndExpectedText = [
            'viewTodaySales' => 'DRILL-SALE-1',
            'viewStorefrontPending' => 'DRILL-PENDING-1',
            'viewTodayPurchases' => 'DRILL-PUR-1',
            'viewCustomerPayments' => 'Drilldown Cash',
            'viewSupplierPayments' => 'Drilldown Cash',
            'viewTodayExpenses' => 'Drilldown note',
            'viewCustomerDue' => 'Drilldown Due Customer',
            'viewSupplierPayable' => 'Drilldown Payable Supplier',
            'viewLowStock' => 'Drilldown Low Stock Product',
            'viewComingSoon' => 'Drilldown Coming Soon Product',
        ];

        $component = Livewire::actingAs($user)->test(BusinessOverview::class);

        foreach ($actionsAndExpectedText as $action => $expectedText) {
            $component
                ->mountAction($action)
                ->assertActionMounted($action);

            $modalHtml = $component->getMountedActionModalHtml();

            $this->assertStringContainsString(
                $expectedText,
                $modalHtml,
                "Modal for action [{$action}] did not contain expected text [{$expectedText}]."
            );

            $component->unmountAction();
        }
    }

    public function test_account_balance_stat_links_straight_to_the_accounts_resource_instead_of_a_modal(): void
    {
        $user = User::factory()->create();
        $this->seedDrilldownData();

        $widget = new class extends BusinessOverview
        {
            public function statsForTest(): array
            {
                return $this->getStats();
            }
        };

        $this->actingAs($user);

        $accountBalanceStat = collect($widget->statsForTest())
            ->first(fn ($stat) => $stat->getLabel() === 'Account Balance');

        $this->assertNotNull($accountBalanceStat);
        $this->assertNotNull($accountBalanceStat->getUrl());
        $this->assertStringContainsString('/admin/finance/accounts', $accountBalanceStat->getUrl());
    }

    /**
     * Regression guard: Filament's table-filter query-string alias is
     * `filters`, not the `tableFilters` property name — a "see all" link
     * built with the wrong key silently opens the list completely
     * unfiltered instead of erroring, so this exercises the real HTTP
     * request end to end rather than just checking the URL string shape.
     */
    public function test_the_storefront_pending_see_all_link_actually_filters_the_orders_list(): void
    {
        $user = User::factory()->create();
        $this->seedDrilldownData();

        $modalHtml = Livewire::actingAs($user)->test(BusinessOverview::class)
            ->mountAction('viewStorefrontPending')
            ->getMountedActionModalHtml();

        $this->assertMatchesRegularExpression('/href="([^"]*\/admin\/sales\/orders\?[^"]*)"/', $modalHtml);
        preg_match('/href="([^"]*\/admin\/sales\/orders\?[^"]*)"/', $modalHtml, $matches);
        $seeAllUrl = html_entity_decode($matches[1]);

        $this->assertStringContainsString('filters%5Bstatus%5D%5Bvalue%5D=draft', $seeAllUrl);

        $this->actingAs($user)
            ->get($seeAllUrl)
            ->assertOk()
            ->assertSee('DRILL-PENDING-1')
            ->assertSee('Active filters')
            ->assertSee('1 result');
    }

    private function seedDrilldownData(): void
    {
        $account = Account::query()->create([
            'name' => 'Drilldown Cash',
            'type' => 'cash',
            'opening_balance' => 5000,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Drilldown Category',
            'slug' => 'drilldown-category',
        ]);

        Product::query()->create([
            'name' => 'Drilldown Low Stock Product',
            'sku' => 'DRILL-LOW-SKU',
            'unit' => 'pcs',
            'cost_price' => 10,
            'sale_price' => 20,
            'price' => 20,
            'stock' => 1,
            'reorder_level' => 5,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'category_id' => $category->id,
        ]);

        Product::query()->create([
            'name' => 'Drilldown Coming Soon Product',
            'sku' => 'DRILL-SOON-SKU',
            'unit' => 'pcs',
            'cost_price' => 10,
            'sale_price' => 20,
            'price' => 20,
            'stock' => 0,
            'reorder_level' => 0,
            'is_active' => true,
            'status' => Product::STATUS_COMING_SOON,
            'category_id' => $category->id,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Drilldown Due Customer',
            'phone' => '01700000001',
            'opening_balance' => 500,
            'is_active' => true,
        ]);
        $customer->forceFill(['current_balance' => 500])->saveQuietly();

        $supplier = Supplier::query()->create([
            'name' => 'Drilldown Payable Supplier',
            'phone' => '01800000001',
            'opening_balance' => 800,
            'is_active' => true,
        ]);
        $supplier->forceFill(['current_balance' => 800])->saveQuietly();

        Order::query()->create([
            'order_number' => 'DRILL-SALE-1',
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 100,
            'status' => Order::STATUS_COMPLETED,
        ]);

        Order::query()->create([
            'order_number' => 'DRILL-PENDING-1',
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 200,
            'source' => Order::SOURCE_STOREFRONT,
            'status' => Order::STATUS_DRAFT,
        ]);

        Purchase::query()->create([
            'purchase_number' => 'DRILL-PUR-1',
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'received',
        ]);

        TransactionLedger::query()->create([
            'account_id' => $account->id,
            'type' => 'customer_payment',
            'direction' => 'in',
            'amount' => 150,
            'transaction_date' => now()->toDateString(),
            'note' => 'Drilldown note customer payment',
        ]);

        TransactionLedger::query()->create([
            'account_id' => $account->id,
            'type' => 'supplier_payment',
            'direction' => 'out',
            'amount' => 90,
            'transaction_date' => now()->toDateString(),
            'note' => 'Drilldown note supplier payment',
        ]);

        $expenseCategory = ExpenseCategory::query()->create([
            'name' => 'Drilldown Expense Category',
            'slug' => 'drilldown-expense-category',
        ]);

        Expense::query()->create([
            'expense_number' => 'DRILL-EXP-1',
            'expense_category_id' => $expenseCategory->id,
            'account_id' => $account->id,
            'amount' => 40,
            'expense_date' => now()->toDateString(),
            'note' => 'Drilldown note expense',
        ]);

    }
}
