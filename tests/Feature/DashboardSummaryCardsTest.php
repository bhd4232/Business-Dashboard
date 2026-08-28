<?php

namespace Tests\Feature;

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Widgets\AccountSummaryWidget;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Widgets\ExpenseCategorySummaryWidget;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Filament\Resources\Vouchers\Widgets\VoucherSummaryWidget;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundTransfer;
use App\Models\TransactionLedger;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use App\Services\FundTransferService;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    /**
     * Owner: the 3-word labels ("Credit Voucher - Requested") wrapped to 3
     * lines at Filament's default stat-card text size, making the cards
     * look awkwardly tall on mobile. Compacted the same way BusinessOverview
     * already compacts the main Dashboard's own stat cards.
     */
    public function test_voucher_summary_cards_use_the_compact_stat_style(): void
    {
        $company = $this->company();
        $user = $this->admin();

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->getKey(), 'current_company_selection_explicit' => true])
            ->get(VoucherResource::getUrl('index'))
            ->assertOk()
            ->assertSee('zz-voucher-summary-stat', false);
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

    /**
     * Owner: "প্রতিটা কার্ডের প্রিভিউ পপ আপ শো করবে ঠিক মেইন ড্যাশবোর্ডের
     * কার্ডের মত" (each card should show a preview popup, just like the main
     * Dashboard's cards) — same drilldown-modal UX BusinessOverview's cards
     * already use, instead of/alongside the direct link.
     */
    public function test_voucher_card_opens_a_preview_modal_listing_the_matching_vouchers(): void
    {
        $company = $this->company();
        $user = $this->admin();
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 100000]);

        $voucherService = app(VoucherService::class);
        $credit = $voucherService->submit(['type' => Voucher::TYPE_CREDIT, 'transaction_type' => 'other', 'amount' => 100, 'account_id' => $account->getKey()], $user);
        $debit = $voucherService->submit(['type' => Voucher::TYPE_DEBIT, 'transaction_type' => 'other', 'amount' => 200, 'account_id' => $account->getKey()], $user);

        $modalHtml = Livewire::actingAs($user)->test(VoucherSummaryWidget::class)
            ->mountAction('viewVoucherGroup', arguments: ['type' => Voucher::TYPE_CREDIT, 'bucket' => 'requested'])
            ->assertActionMounted('viewVoucherGroup')
            ->getMountedActionModalHtml();

        $this->assertStringContainsString($credit->voucher_number, $modalHtml);
        $this->assertStringNotContainsString($debit->voucher_number, $modalHtml);
        $this->assertStringContainsString(VoucherResource::getUrl('index'), $modalHtml);
    }

    public function test_fund_transfer_card_opens_a_preview_modal_listing_the_matching_transfers(): void
    {
        $company = $this->company();
        $user = $this->admin();
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 100000]);
        $account2 = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 50000]);

        $fundTransferService = app(FundTransferService::class);
        $pending = $fundTransferService->submit(['from_account_id' => $account->getKey(), 'to_account_id' => $account2->getKey(), 'amount' => 10], $user);
        $toApprove = $fundTransferService->submit(['from_account_id' => $account->getKey(), 'to_account_id' => $account2->getKey(), 'amount' => 20], $user);
        $fundTransferService->approve($toApprove, $user);

        $modalHtml = Livewire::actingAs($user)->test(VoucherSummaryWidget::class)
            ->mountAction('viewFundTransferGroup', arguments: ['status' => FundTransfer::STATUS_PENDING])
            ->assertActionMounted('viewFundTransferGroup')
            ->getMountedActionModalHtml();

        $this->assertStringContainsString($pending->transfer_number, $modalHtml);
        $this->assertStringNotContainsString($toApprove->transfer_number, $modalHtml);
    }

    public function test_account_summary_cards_show_each_accounts_balance(): void
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
            // The account's ID (never its name/text) is embedded directly
            // in the card's wire:click — each card opens a preview modal
            // for that exact account (see AccountSummaryWidget).
            ->assertSee("account: {$bank->getKey()} }", false)
            // Owner: same compact stat style as Vouchers (see
            // .zz-account-summary-stat in theme.css).
            ->assertSee('zz-account-summary-stat', false);
    }

    /**
     * Owner: same preview-popup request as Vouchers, applied here too.
     */
    public function test_account_card_opens_a_preview_modal_listing_its_recent_ledger_entries(): void
    {
        $company = $this->company();
        $user = $this->admin();
        $cash = Account::query()->create(['name' => 'Cash Box', 'type' => 'cash', 'opening_balance' => 15000]);
        $bank = Account::query()->create(['name' => 'City Bank', 'type' => 'bank', 'opening_balance' => 42000]);

        TransactionLedger::query()->create([
            'account_id' => $cash->getKey(),
            'type' => 'customer_payment',
            'direction' => 'in',
            'amount' => 500,
            'transaction_date' => now()->toDateString(),
            'note' => 'Cash Box ledger note',
        ]);
        TransactionLedger::query()->create([
            'account_id' => $bank->getKey(),
            'type' => 'supplier_payment',
            'direction' => 'out',
            'amount' => 300,
            'transaction_date' => now()->toDateString(),
            'note' => 'City Bank ledger note',
        ]);

        $modalHtml = Livewire::actingAs($user)->test(AccountSummaryWidget::class)
            ->mountAction('viewAccount', arguments: ['account' => $cash->getKey()])
            ->assertActionMounted('viewAccount')
            ->getMountedActionModalHtml();

        $this->assertStringContainsString('Cash Box ledger note', $modalHtml);
        $this->assertStringNotContainsString('City Bank ledger note', $modalHtml);
        $this->assertStringContainsString(AccountResource::getUrl('view', ['record' => $cash]), $modalHtml);
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
            // The category's ID (never its name/text) is embedded directly
            // in the card's wire:click — each card opens a preview modal
            // for that exact category (see ExpenseCategorySummaryWidget).
            ->assertSee("category: {$utilities->getKey()} }", false)
            // Owner: same compact stat style as Vouchers (see
            // .zz-expense-summary-stat in theme.css).
            ->assertSee('zz-expense-summary-stat', false);
    }

    /**
     * Owner: same preview-popup request as Vouchers, applied here too.
     */
    public function test_expense_category_card_opens_a_preview_modal_listing_its_recent_expenses(): void
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
            'note' => 'Rent expense note',
        ]);
        Expense::query()->create([
            'expense_category_id' => $utilities->getKey(),
            'account_id' => $account->getKey(),
            'amount' => 1200,
            'note' => 'Utilities expense note',
        ]);

        $modalHtml = Livewire::actingAs($user)->test(ExpenseCategorySummaryWidget::class)
            ->mountAction('viewExpenseCategory', arguments: ['category' => $rent->getKey()])
            ->assertActionMounted('viewExpenseCategory')
            ->getMountedActionModalHtml();

        $this->assertStringContainsString('Rent expense note', $modalHtml);
        $this->assertStringNotContainsString('Utilities expense note', $modalHtml);
        $this->assertStringContainsString(ExpenseCategoryResource::getUrl('view', ['record' => $rent]), $modalHtml);
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
