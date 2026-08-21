<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the GeneratesSequentialNumber concern (audit M-1): when a concurrent
 * request grabs the number an insert was about to use, the insert must retry
 * and mint a fresh unique number instead of failing on the UNIQUE index.
 */
class SequentialNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $first = Order::query()->create([
            'customer_name' => 'First Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        // The next order's first-choice number is stolen once by a simulated
        // concurrent request, forcing the retry path.
        $stole = false;
        Order::creating(function (Order $order) use (&$stole): void {
            if ($stole || blank($order->order_number)) {
                return;
            }

            $stole = true;

            Order::query()->create([
                'order_number' => $order->order_number,
                'customer_name' => 'Concurrent Buyer',
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
            ]);
        });

        $second = Order::query()->create([
            'customer_name' => 'Second Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertSame(1, Order::query()->where('order_number', $second->order_number)->count());
        $this->assertSame(3, Order::query()->distinct()->count('order_number'));
    }

    public function test_purchase_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $supplier = Supplier::query()->create(['name' => 'Concurrency Supplier']);

        $first = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $stole = false;
        Purchase::creating(function (Purchase $purchase) use (&$stole, $supplier): void {
            if ($stole || blank($purchase->purchase_number)) {
                return;
            }

            $stole = true;

            Purchase::query()->create([
                'purchase_number' => $purchase->purchase_number,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'status' => 'draft',
            ]);
        });

        $second = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->purchase_number, $second->purchase_number);
        $this->assertSame(1, Purchase::query()->where('purchase_number', $second->purchase_number)->count());
        $this->assertSame(3, Purchase::query()->distinct()->count('purchase_number'));
    }

    public function test_customer_payment_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);
        $customer = Customer::query()->create(['name' => 'Concurrency Customer', 'opening_balance' => 1000]);
        $customer->syncCurrentBalance();

        $first = CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 10,
        ]);

        $stole = false;
        CustomerPayment::creating(function (CustomerPayment $payment) use (&$stole, $customer, $account): void {
            if ($stole || blank($payment->payment_number)) {
                return;
            }

            $stole = true;

            CustomerPayment::query()->create([
                'payment_number' => $payment->payment_number,
                'customer_id' => $customer->id,
                'account_id' => $account->id,
                'amount' => 10,
            ]);
        });

        $second = CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'account_id' => $account->id,
            'amount' => 10,
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->payment_number, $second->payment_number);
        $this->assertSame(3, CustomerPayment::query()->distinct()->count('payment_number'));
    }

    public function test_supplier_payment_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 1000]);
        $supplier = Supplier::query()->create(['name' => 'Concurrency Supplier', 'opening_balance' => 1000]);
        $supplier->syncCurrentBalance();

        $first = SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 10,
        ]);

        $stole = false;
        SupplierPayment::creating(function (SupplierPayment $payment) use (&$stole, $supplier, $account): void {
            if ($stole || blank($payment->payment_number)) {
                return;
            }

            $stole = true;

            SupplierPayment::query()->create([
                'payment_number' => $payment->payment_number,
                'supplier_id' => $supplier->id,
                'account_id' => $account->id,
                'amount' => 10,
            ]);
        });

        $second = SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'amount' => 10,
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->payment_number, $second->payment_number);
        $this->assertSame(3, SupplierPayment::query()->distinct()->count('payment_number'));
    }

    public function test_expense_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 1000]);
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent', 'is_active' => true]);

        $first = Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 10,
        ]);

        $stole = false;
        Expense::creating(function (Expense $expense) use (&$stole, $category, $account): void {
            if ($stole || blank($expense->expense_number)) {
                return;
            }

            $stole = true;

            Expense::query()->create([
                'expense_number' => $expense->expense_number,
                'expense_category_id' => $category->id,
                'account_id' => $account->id,
                'amount' => 10,
            ]);
        });

        $second = Expense::query()->create([
            'expense_category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 10,
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->expense_number, $second->expense_number);
        $this->assertSame(3, Expense::query()->distinct()->count('expense_number'));
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => 'Concurrency Co',
            'slug' => 'concurrency-co',
            'invoice_prefix' => 'CNC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }
}
