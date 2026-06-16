<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use App\Filament\Resources\TransactionLedgers\TransactionLedgerResource;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseSixPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Users');
    }

    public function test_user_create_form_renders_role_options(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('zz-role-select')
            ->assertSee('createOption')
            ->assertSee('Super Admin')
            ->assertSee('Sales Staff')
            ->assertSee('Inventory Staff');
    }

    public function test_sales_staff_cannot_access_users_or_accounts(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/accounts')->assertForbidden();
    }

    public function test_sales_staff_can_view_reports_but_cannot_export_reports(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/reports')->assertOk();
        $this->actingAs($user)->get('/admin/reports/export/sales')->assertForbidden();
    }

    public function test_custom_role_permissions_are_used(): void
    {
        UserRole::query()->create([
            'name' => 'Purchase Viewer',
            'slug' => 'purchase_viewer',
            'permissions' => ['purchasing.view'],
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'purchase_viewer',
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasPermission('purchasing.view'));
        $this->assertFalse($user->hasPermission('purchasing.create'));
    }

    public function test_super_admin_can_manage_custom_user_roles(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        UserRole::query()->create([
            'name' => 'Report Operator',
            'slug' => 'report_operator',
            'permissions' => ['dashboard.view', 'reports.view', 'reports.export'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/user-roles')
            ->assertOk()
            ->assertSee('User Roles')
            ->assertSee('Dashboard: View')
            ->assertSee('Reports: View')
            ->assertSee('aria-hidden="true">+</span>1', false)
            ->assertSee('Reports: Export');

        $this->actingAs($admin)
            ->get('/admin/user-roles/create')
            ->assertOk()
            ->assertSee('Role Details')
            ->assertSee('Permissions');
    }

    public function test_user_without_role_does_not_become_super_admin(): void
    {
        $user = User::factory()->make([
            'role' => null,
            'is_active' => true,
        ]);

        $this->assertSame('sales_staff', $user->effectiveRole());
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->canManageUsers());
    }

    public function test_inactive_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => false,
        ]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_audit_log_records_authenticated_model_changes(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $category = Category::query()->create([
            'name' => 'Audit Category',
            'slug' => 'audit-category',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'created',
            'auditable_type' => Category::class,
            'auditable_id' => $category->id,
        ]);

        $this->assertSame('Audit Category', AuditLog::query()->latest('id')->first()?->new_values['name']);
    }

    public function test_audit_log_records_stock_adjustment_reason(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Audited Stock Product',
            'sku' => 'AUDIT-STOCK-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);

        $this->actingAs($admin);

        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 5,
            'reason' => 'Physical stock count correction',
        ]);

        $auditLog = AuditLog::query()
            ->where('auditable_type', StockMovement::class)
            ->where('auditable_id', $movement->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame('created', $auditLog->action);
        $this->assertSame('Physical stock count correction', $auditLog->new_values['reason']);
    }

    public function test_audit_log_records_customer_payment_edits(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Audit Customer',
            'opening_balance' => 500,
            'is_active' => true,
        ]);
        $account = Account::query()->create([
            'name' => 'Audit Cash',
            'opening_balance' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $payment = CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'note' => 'Initial payment',
        ]);

        $payment->update([
            'amount' => 80,
            'note' => 'Edited after bank confirmation',
        ]);

        $auditLog = AuditLog::query()
            ->where('action', 'updated')
            ->where('auditable_type', CustomerPayment::class)
            ->where('auditable_id', $payment->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertEquals(100, $auditLog->old_values['amount']);
        $this->assertEquals(80, $auditLog->new_values['amount']);
        $this->assertSame('Edited after bank confirmation', $auditLog->new_values['note']);
    }

    public function test_audit_log_records_supplier_payment_and_expense_changes(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'name' => 'Audit Supplier',
            'opening_balance' => 500,
            'is_active' => true,
        ]);
        $account = Account::query()->create([
            'name' => 'Audit Bank',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);
        $category = ExpenseCategory::query()->create([
            'name' => 'Audit Expense',
            'slug' => 'audit-expense',
        ]);

        $this->actingAs($admin);

        $supplierPayment = SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 150,
            'payment_date' => now()->toDateString(),
            'method' => 'bank',
        ]);
        $supplierPayment->update(['amount' => 120]);

        $expense = Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 70,
            'expense_date' => now()->toDateString(),
            'note' => 'Original expense',
        ]);
        $expense->delete();

        $supplierAudit = AuditLog::query()
            ->where('action', 'updated')
            ->where('auditable_type', SupplierPayment::class)
            ->where('auditable_id', $supplierPayment->id)
            ->latest('id')
            ->firstOrFail();
        $expenseAudit = AuditLog::query()
            ->where('action', 'deleted')
            ->where('auditable_type', Expense::class)
            ->where('auditable_id', $expense->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(150, $supplierAudit->old_values['amount']);
        $this->assertEquals(120, $supplierAudit->new_values['amount']);
        $this->assertEquals(70, $expenseAudit->old_values['amount']);
        $this->assertNull($expenseAudit->new_values);
    }

    public function test_sensitive_financial_delete_permissions_are_super_admin_only(): void
    {
        $accountant = User::factory()->create([
            'role' => 'accountant',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($accountant);

        $this->assertFalse(CustomerPaymentResource::canDeleteAny());
        $this->assertFalse(SupplierPaymentResource::canDeleteAny());
        $this->assertFalse(ExpenseResource::canDeleteAny());
        $this->assertFalse(TransactionLedgerResource::canDeleteAny());

        $this->actingAs($admin);

        $this->assertTrue(CustomerPaymentResource::canDeleteAny());
        $this->assertTrue(SupplierPaymentResource::canDeleteAny());
        $this->assertTrue(ExpenseResource::canDeleteAny());
        $this->assertFalse(TransactionLedgerResource::canDeleteAny());
    }

    public function test_current_user_cannot_deactivate_self(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);

        $admin->update(['is_active' => false]);
    }

    public function test_last_active_super_admin_cannot_be_downgraded(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);

        $admin->update(['role' => 'manager']);
    }

    public function test_sensitive_payment_edit_is_restricted(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Sensitive Customer',
            'opening_balance' => 200,
            'is_active' => true,
        ]);
        $account = Account::query()->create([
            'name' => 'Sensitive Cash',
            'type' => 'cash',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);
        $payment = CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 50,
            'payment_date' => now()->toDateString(),
        ]);

        $salesStaff = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);
        $accountant = User::factory()->create([
            'role' => 'accountant',
            'is_active' => true,
        ]);

        $this->actingAs($salesStaff)->get("/admin/customer-payments/{$payment->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->get("/admin/customer-payments/{$payment->id}/edit")->assertOk();
    }

    public function test_audit_log_detail_page_renders(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        Category::query()->create([
            'name' => 'Audit Detail Category',
            'slug' => 'audit-detail-category',
        ]);

        $auditLog = AuditLog::query()->latest('id')->firstOrFail();

        $this->get("/admin/audit-logs/{$auditLog->id}")
            ->assertOk()
            ->assertSee('Audit Information')
            ->assertSee('Audit Detail Category');
    }
}
