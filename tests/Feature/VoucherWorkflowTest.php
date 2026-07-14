<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\FundSource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoucherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Voucher Co', 'slug' => 'voucher-co', 'invoice_prefix' => 'VCH',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    protected function user(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    public function test_credit_voucher_flow_pending_verified_approved_creates_customer_payment(): void
    {
        $this->company();
        $customer = Customer::query()->create(['name' => 'Voucher Customer', 'phone' => '01711111111']);
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_CREDIT,
            'transaction_type' => 'customer_payment',
            'amount' => 500,
            'customer_id' => $customer->getKey(),
            'account_id' => $account->getKey(),
            'payment_method' => 'cash',
        ], $user);

        $this->assertSame(Voucher::STATUS_PENDING, $voucher->status);

        // Approving straight from pending is rejected for a credit voucher.
        $this->expectException(ValidationException::class);
        $service->approve($voucher, $user);
    }

    public function test_credit_voucher_verify_then_approve_creates_customer_payment_and_updates_due(): void
    {
        $this->company();
        $customer = Customer::query()->create(['name' => 'Voucher Customer', 'phone' => '01711111111', 'opening_balance' => 1000]);
        $customer->syncCurrentBalance();
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_CREDIT,
            'transaction_type' => 'customer_payment',
            'amount' => 500,
            'customer_id' => $customer->getKey(),
            'account_id' => $account->getKey(),
            'payment_method' => 'cash',
        ], $user);

        $service->verify($voucher, $user);
        $this->assertSame(Voucher::STATUS_VERIFIED, $voucher->fresh()->status);

        $service->approve($voucher, $user);
        $voucher->refresh();

        $this->assertSame(Voucher::STATUS_APPROVED, $voucher->status);
        $this->assertSame(\App\Models\CustomerPayment::class, $voucher->resulting_model_type);
        $this->assertNotNull($voucher->resulting_model_id);
        $this->assertSame('500.00', $account->fresh()->current_balance);
        $this->assertSame(500.0, (float) $customer->fresh()->current_balance);
    }

    public function test_debit_voucher_can_approve_directly_from_pending(): void
    {
        $this->company();
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent']);
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 10000]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'business_expense',
            'amount' => 2000,
            'expense_category_id' => $category->getKey(),
            'account_id' => $account->getKey(),
        ], $user);

        $service->approve($voucher, $user);
        $voucher->refresh();

        $this->assertSame(Voucher::STATUS_APPROVED, $voucher->status);
        $this->assertSame(\App\Models\Expense::class, $voucher->resulting_model_type);
        $this->assertSame('8000.00', $account->fresh()->current_balance);
    }

    public function test_reject_requires_a_reason_and_does_not_book_anything(): void
    {
        $this->company();
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent']);
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 10000]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'business_expense',
            'amount' => 2000,
            'expense_category_id' => $category->getKey(),
            'account_id' => $account->getKey(),
        ], $user);

        $this->expectException(ValidationException::class);
        $service->reject($voucher, '', $user);
    }

    public function test_reject_with_reason_marks_rejected_and_leaves_account_untouched(): void
    {
        $this->company();
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent']);
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 10000]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'business_expense',
            'amount' => 2000,
            'expense_category_id' => $category->getKey(),
            'account_id' => $account->getKey(),
        ], $user);

        $service->reject($voucher, 'Missing bill', $user);
        $voucher->refresh();

        $this->assertSame(Voucher::STATUS_REJECTED, $voucher->status);
        $this->assertSame('Missing bill', $voucher->rejection_reason);
        $this->assertNull($voucher->resulting_model_type);
        $this->assertSame('10000.00', $account->fresh()->current_balance);
    }

    public function test_only_pending_voucher_can_be_cancelled(): void
    {
        $this->company();
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent']);
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 10000]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'business_expense',
            'amount' => 2000,
            'expense_category_id' => $category->getKey(),
            'account_id' => $account->getKey(),
        ], $user);

        $service->approve($voucher, $user);

        $this->expectException(ValidationException::class);
        $service->cancel($voucher);
    }

    public function test_fund_transfer_transaction_type_is_rejected_on_voucher_submission(): void
    {
        $this->company();
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 10000]);
        $user = $this->user();

        $this->expectException(ValidationException::class);

        app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'fund_transfer',
            'amount' => 1000,
            'account_id' => $account->getKey(),
        ], $user);
    }

    public function test_supplier_payment_voucher_creates_supplier_payment(): void
    {
        $this->company();
        $supplier = Supplier::query()->create(['name' => 'Voucher Supplier', 'opening_balance' => 2000]);
        $supplier->syncCurrentBalance();
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 5000]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'supplier_payment',
            'amount' => 1000,
            'supplier_id' => $supplier->getKey(),
            'account_id' => $account->getKey(),
        ], $user);

        $service->approve($voucher, $user);

        $this->assertSame(\App\Models\SupplierPayment::class, $voucher->fresh()->resulting_model_type);
        $this->assertSame('4000.00', $account->fresh()->current_balance);
    }
}
