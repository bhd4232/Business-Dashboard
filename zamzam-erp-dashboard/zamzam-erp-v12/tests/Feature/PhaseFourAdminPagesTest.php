<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFourAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_four_admin_pages_render_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create(['name' => 'Admin Cash', 'opening_balance' => 1000]);
        $customer = Customer::query()->create(['name' => 'Admin Customer', 'opening_balance' => 200]);
        $supplier = Supplier::query()->create(['name' => 'Admin Supplier', 'opening_balance' => 200]);
        $category = ExpenseCategory::query()->create(['name' => 'Admin Expense', 'slug' => 'admin-expense']);

        $customerPayment = CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 100,
            'payment_date' => now(),
        ]);
        $supplierPayment = SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 50,
            'payment_date' => now(),
        ]);
        $expense = Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 25,
            'expense_date' => now(),
        ]);

        foreach ([
            '/admin/accounts',
            '/admin/customer-payments',
            '/admin/supplier-payments',
            '/admin/expense-categories',
            '/admin/expenses',
            '/admin/transaction-ledgers',
            "/admin/customer-payments/{$customerPayment->id}",
            "/admin/supplier-payments/{$supplierPayment->id}",
            "/admin/expenses/{$expense->id}",
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
