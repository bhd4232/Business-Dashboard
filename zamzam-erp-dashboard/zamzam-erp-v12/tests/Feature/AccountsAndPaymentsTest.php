<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TransactionLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountsAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_payment_reduces_customer_due_and_increases_account_balance(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 100]);
        $customer = Customer::query()->create(['name' => 'Customer', 'opening_balance' => 20]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'subtotal' => 500,
            'total_amount' => 500,
            'due_amount' => 500,
            'status' => 'completed',
        ]);
        $order->forceFill([
            'subtotal' => 500,
            'total_amount' => 500,
            'due_amount' => 500,
        ])->saveQuietly();
        $customer->syncCurrentBalance();

        CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 150,
            'payment_date' => now(),
            'method' => 'cash',
        ]);

        $this->assertSame('370.00', $customer->refresh()->current_balance);
        $this->assertSame('250.00', $account->refresh()->current_balance);
        $this->assertDatabaseHas('transaction_ledgers', [
            'account_id' => $account->id,
            'type' => 'customer_payment',
            'direction' => 'in',
            'amount' => 150,
            'reference_type' => CustomerPayment::class,
        ]);
    }

    public function test_supplier_payment_reduces_supplier_payable_and_decreases_account_balance(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 500]);
        $supplier = Supplier::query()->create(['name' => 'Supplier', 'opening_balance' => 50]);

        $purchase = \App\Models\Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'total_amount' => 200,
            'due_amount' => 200,
            'status' => 'received',
        ]);
        $purchase->forceFill(['due_amount' => 200])->saveQuietly();
        $supplier->syncCurrentBalance();

        SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 120,
            'payment_date' => now(),
            'method' => 'cash',
        ]);

        $this->assertSame('130.00', $supplier->refresh()->current_balance);
        $this->assertSame('380.00', $account->refresh()->current_balance);
        $this->assertDatabaseHas('transaction_ledgers', [
            'account_id' => $account->id,
            'type' => 'supplier_payment',
            'direction' => 'out',
            'amount' => 120,
            'reference_type' => SupplierPayment::class,
        ]);
    }

    public function test_expense_decreases_account_balance_and_creates_ledger_entry(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 300]);
        $category = ExpenseCategory::query()->create([
            'name' => 'Office Rent',
            'slug' => 'office-rent',
        ]);

        Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 75,
            'expense_date' => now(),
        ]);

        $this->assertSame('225.00', $account->refresh()->current_balance);
        $this->assertSame(1, TransactionLedger::query()->where('type', 'expense')->count());
        $this->assertDatabaseHas('transaction_ledgers', [
            'account_id' => $account->id,
            'type' => 'expense',
            'direction' => 'out',
            'amount' => 75,
            'reference_type' => Expense::class,
        ]);
    }

    public function test_customer_payment_cannot_exceed_customer_due(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 100]);
        $customer = Customer::query()->create(['name' => 'Customer', 'opening_balance' => 50]);

        $this->expectException(ValidationException::class);

        CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 60,
            'payment_date' => now(),
        ]);
    }

    public function test_supplier_payment_cannot_exceed_supplier_payable(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 100]);
        $supplier = Supplier::query()->create(['name' => 'Supplier', 'opening_balance' => 50]);

        $this->expectException(ValidationException::class);

        SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 60,
            'payment_date' => now(),
        ]);
    }

    public function test_supplier_payment_cannot_make_account_balance_negative(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 40]);
        $supplier = Supplier::query()->create(['name' => 'Supplier', 'opening_balance' => 100]);

        $this->expectException(ValidationException::class);

        SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 50,
            'payment_date' => now(),
        ]);
    }

    public function test_expense_cannot_make_account_balance_negative(): void
    {
        $account = Account::query()->create(['name' => 'Cash', 'opening_balance' => 40]);
        $category = ExpenseCategory::query()->create([
            'name' => 'Office Rent',
            'slug' => 'office-rent',
        ]);

        $this->expectException(ValidationException::class);

        Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 50,
            'expense_date' => now(),
        ]);
    }
}
