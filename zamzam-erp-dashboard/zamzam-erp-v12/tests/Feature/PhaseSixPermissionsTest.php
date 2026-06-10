<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
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
