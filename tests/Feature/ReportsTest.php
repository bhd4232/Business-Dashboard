<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->seedReportData();

        $this->actingAs($user)
            ->get('/admin/reports?date_from=2026-06-03&date_to=2026-06-03')
            ->assertOk()
            ->assertSee('Sales Report')
            ->assertSee('INV-REPORT-1')
            ->assertSee('Export CSV');
    }

    public function test_reports_page_switches_active_report_from_query_string(): void
    {
        $user = User::factory()->create();

        $this->seedReportData();

        $this->actingAs($user)
            ->get('/admin/reports?report_type=profit&date_from=2026-06-03&date_to=2026-06-03')
            ->assertOk()
            ->assertSee('Product Profit Report')
            ->assertSee('Report Product')
            ->assertSee('BDT 100.00');
    }

    public function test_sales_report_exports_csv(): void
    {
        $user = User::factory()->create();

        $reportDate = '2026-06-03';

        $this->seedReportData($reportDate);
        [$from, $to] = app(ReportService::class)->dateRange($reportDate, $reportDate);

        $this->assertCount(1, app(ReportService::class)->sales($from, $to));

        $response = $this->actingAs($user)
            ->get("/admin/reports/export/sales?date_from={$reportDate}&date_to={$reportDate}");

        $response->assertOk();
        $response->assertDownload('sales-report.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Invoice', $content);
        $this->assertStringContainsString('INV-REPORT-1', $content);
        $this->assertStringContainsString('Report Customer', $content);
    }

    public function test_purchase_report_includes_dynamic_custom_cost_fields(): void
    {
        $user = User::factory()->create();

        $reportDate = '2026-06-03';

        $this->seedReportData($reportDate);

        $this->actingAs($user)
            ->get("/admin/reports?report_type=purchases&date_from={$reportDate}&date_to={$reportDate}")
            ->assertOk()
            ->assertSee('Warehouse Charge')
            ->assertSee('China to BD Costs')
            ->assertSee('BDT 25.00');

        $response = $this->actingAs($user)
            ->get("/admin/reports/export/purchases?date_from={$reportDate}&date_to={$reportDate}");

        $response->assertOk();
        $response->assertDownload('purchase-report.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Warehouse Charge', $content);
        $this->assertStringContainsString('China to BD Cost Total', $content);
        $this->assertStringContainsString('Landed Cost Total', $content);
        $this->assertStringContainsString('PUR-REPORT-1', $content);
    }

    public function test_purchase_list_shows_custom_cost_summary(): void
    {
        $user = User::factory()->create();

        $this->seedReportData();

        $this->actingAs($user)
            ->get('/admin/purchases')
            ->assertOk()
            ->assertSee('China to BD Costs');
    }

    private function seedReportData(string $reportDate = '2026-06-03'): void
    {
        $account = Account::query()->create([
            'name' => 'Report Cash',
            'type' => 'cash',
            'opening_balance' => 10000,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Report Category',
            'slug' => 'report-category',
        ]);
        $product = Product::query()->create([
            'name' => 'Report Product',
            'sku' => 'REPORT-SKU',
            'unit' => 'pcs',
            'cost_price' => 50,
            'sale_price' => 100,
            'price' => 100,
            'stock' => 20,
            'reorder_level' => 5,
            'is_active' => true,
            'category_id' => $category->id,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 20,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'note' => 'Report test opening stock',
        ]);

        $customer = Customer::query()->create([
            'name' => 'Report Customer',
            'phone' => '01700000000',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Report Supplier',
            'phone' => '01800000000',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'INV-REPORT-1',
            'customer_id' => $customer->id,
            'order_date' => $reportDate,
            'paid_amount' => 50,
            'status' => 'completed',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $purchase = Purchase::query()->create([
            'purchase_number' => 'PUR-REPORT-1',
            'supplier_id' => $supplier->id,
            'purchase_date' => $reportDate,
            'freight_to_ctg' => 10,
            'custom_costs' => [
                ['label' => 'Warehouse Charge', 'amount' => 15],
            ],
            'paid_amount' => 30,
            'status' => 'received',
        ]);
        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_cost' => 50,
        ]);

        $expenseCategory = ExpenseCategory::query()->create([
            'name' => 'Report Expense Category',
            'slug' => 'report-expense-category',
        ]);

        Expense::query()->create([
            'expense_number' => 'EXP-REPORT-1',
            'expense_category_id' => $expenseCategory->id,
            'account_id' => $account->id,
            'amount' => 25,
            'expense_date' => $reportDate,
        ]);
    }
}
