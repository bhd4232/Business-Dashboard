<?php

namespace Tests\Feature;

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use App\Services\FundTransferService;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard-style summary cards the owner asked for on three list pages:
 * Vouchers (Credit/Debit/Fund Transfer x Requested/Approved/Rejected
 * counts), Accounts (one card per account showing its live balance), and
 * Expenses (one card per expense category showing its total spend).
 */
class DashboardSummaryCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_voucher_summary_cards_show_correct_counts_per_type_and_status(): void
    {
        $company = $this->company();
        $user = $this->admin();
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 100000]);
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent']);

        $voucherService = app(VoucherService::class);

        // Credit: 1 pending (stays "Requested"), 1 verified+approved, 1 rejected.
        $voucherService->submit(['type' => Voucher::TYPE_CREDIT, 'transaction_type' => 'other', 'amount' => 100, 'account_id' => $account->getKey()], $user);
        $creditToApprove = $voucherService->submit(['type' => Voucher::TYPE_CREDIT, 'transaction_type' => 'other', 'amount' => 200, 'account_id' => $account->getKey()], $user);
        $voucherService->verify($creditToApprove, $user);
        $voucherService->approve($creditToApprove, $user);
        $creditToReject = $voucherService->submit(['type' => Voucher::TYPE_CREDIT, 'transaction_type' => 'other', 'amount' => 300, 'account_id' => $account->getKey()], $user);
        $voucherService->reject($creditToReject, 'Not valid.', $user);

        // Debit: 1 pending, 1 approved directly from pending, 1 rejected.
        $voucherService->submit(['type' => Voucher::TYPE_DEBIT, 'transaction_type' => 'business_expense', 'amount' => 50, 'account_id' => $account->getKey(), 'expense_category_id' => $category->getKey()], $user);
        $debitToApprove = $voucherService->submit(['type' => Voucher::TYPE_DEBIT, 'transaction_type' => 'business_expense', 'amount' => 60, 'account_id' => $account->getKey(), 'expense_category_id' => $category->getKey()], $user);
        $voucherService->approve($debitToApprove, $user);
        $debitToReject = $voucherService->submit(['type' => Voucher::TYPE_DEBIT, 'transaction_type' => 'business_expense', 'amount' => 70, 'account_id' => $account->getKey(), 'expense_category_id' => $category->getKey()], $user);
        $voucherService->reject($debitToReject, 'Not valid.', $user);

        // Fund Transfer: 1 pending, 1 approved, 1 rejected.
        $account2 = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 50000]);
        $fundTransferService = app(FundTransferService::class);
        $fundTransferService->submit(['from_account_id' => $account->getKey(), 'to_account_id' => $account2->getKey(), 'amount' => 10], $user);
        $ftToApprove = $fundTransferService->submit(['from_account_id' => $account->getKey(), 'to_account_id' => $account2->getKey(), 'amount' => 20], $user);
        $fundTransferService->approve($ftToApprove, $user);
        $ftToReject = $fundTransferService->submit(['from_account_id' => $account->getKey(), 'to_account_id' => $account2->getKey(), 'amount' => 30], $user);
        $fundTransferService->reject($ftToReject, $user);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get(VoucherResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Credit Voucher - Requested')
            ->assertSee('Credit Voucher - Approved')
            ->assertSee('Credit Voucher - Rejected')
            ->assertSee('Debit Voucher - Requested')
            ->assertSee('Debit Voucher - Approved')
            ->assertSee('Debit Voucher - Rejected')
            ->assertSee('Fund Transfer - Requested')
            ->assertSee('Fund Transfer - Approved')
            ->assertSee('Fund Transfer - Rejected');
    }

    public function test_voucher_card_link_filters_the_table_to_that_type_and_status(): void
    {
        $company = $this->company();
        $user = $this->admin();
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 100000]);

        $voucherService = app(VoucherService::class);
        $credit = $voucherService->submit(['type' => Voucher::TYPE_CREDIT, 'transaction_type' => 'other', 'amount' => 100, 'account_id' => $account->getKey()], $user);
        $debit = $voucherService->submit(['type' => Voucher::TYPE_DEBIT, 'transaction_type' => 'other', 'amount' => 200, 'account_id' => $account->getKey()], $user);

        $response = $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get(VoucherResource::getUrl('index').'?cardType=credit&cardStatuses=pending,verified');

        $response->assertOk()->assertSee($credit->voucher_number)->assertDontSee($debit->voucher_number);
    }

    public function test_account_summary_cards_show_each_accounts_balance_and_link_to_its_own_page(): void
    {
        $company = $this->company();
        $user = $this->admin();
        Account::query()->create(['name' => 'Cash Box', 'type' => 'cash', 'opening_balance' => 15000]);
        $bank = Account::query()->create(['name' => 'City Bank', 'type' => 'bank', 'opening_balance' => 42000]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get(AccountResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Cash Box')
            ->assertSee('৳ 15,000')
            ->assertSee('City Bank')
            ->assertSee('৳ 42,000')
            ->assertSee(AccountResource::getUrl('view', ['record' => $bank]), false);
    }

    public function test_expense_category_summary_cards_show_total_spend_per_category(): void
    {
        $company = $this->company();
        $user = $this->admin();
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 100000]);
        $rent = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent']);
        $utilities = ExpenseCategory::query()->create(['name' => 'Utilities', 'slug' => 'utilities']);

        Expense::query()->create([
            'expense_category_id' => $rent->getKey(),
            'account_id' => $account->getKey(),
            'amount' => 5000,
        ]);
        Expense::query()->create([
            'expense_category_id' => $rent->getKey(),
            'account_id' => $account->getKey(),
            'amount' => 2500,
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get(ExpenseResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Rent')
            ->assertSee('৳ 7,500')
            ->assertSee('Utilities')
            ->assertSee('৳ 0')
            ->assertSee(ExpenseCategoryResource::getUrl('view', ['record' => $utilities]), false);
    }

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Dashboard Cards Co',
            'slug' => 'dashboard-cards-co-'.uniqid(),
            'invoice_prefix' => 'DCC'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }
}
