<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\FundSource;
use App\Models\FundTransfer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use App\Services\FundTransferService;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Accounting Co', 'slug' => 'accounting-co', 'invoice_prefix' => 'ACC',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    protected function user(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    public function test_inventory_purchase_voucher_never_creates_an_expense(): void
    {
        $this->company();
        $supplier = Supplier::query()->create(['name' => 'Rule1 Supplier']);
        $product = Product::query()->create(['name' => 'Rule1 Product', 'sku' => 'R1-001', 'price' => 100, 'sale_price' => 100, 'stock' => 0]);
        $purchase = Purchase::query()->create(['supplier_id' => $supplier->getKey(), 'purchase_date' => now(), 'status' => 'received']);
        PurchaseItem::query()->create(['purchase_id' => $purchase->getKey(), 'product_id' => $product->getKey(), 'quantity' => 10, 'unit_cost' => 1000]);
        $purchase->refresh();

        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 50000]);
        $fundSource = FundSource::query()->create(['name' => 'Business Cash', 'type' => 'cash', 'account_id' => $account->getKey()]);
        $user = $this->user();

        $expenseCountBefore = Expense::query()->count();

        $voucher = app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'inventory_purchase',
            'amount' => (float) $purchase->total_amount,
            'purchase_id' => $purchase->getKey(),
            'fund_source_id' => $fundSource->getKey(),
        ], $user);

        app(VoucherService::class)->approve($voucher, $user);

        $this->assertSame($expenseCountBefore, Expense::query()->count(), 'inventory_purchase must never create an Expense (Rule 1).');
        $this->assertNotSame(Expense::class, $voucher->fresh()->resulting_model_type);
        $this->assertSame(50000.0 - (float) $purchase->total_amount, (float) $account->fresh()->current_balance);
    }

    public function test_owner_withdrawal_debits_account_and_creates_no_expense(): void
    {
        $this->company();
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 20000]);
        $user = $this->user();
        $expenseCountBefore = Expense::query()->count();

        $voucher = app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'owner_withdrawal',
            'amount' => 5000,
            'account_id' => $account->getKey(),
        ], $user);

        app(VoucherService::class)->approve($voucher, $user);

        $this->assertSame($expenseCountBefore, Expense::query()->count());
        $this->assertSame('15000.00', $account->fresh()->current_balance);
    }

    public function test_fund_transfer_moves_amount_between_accounts_with_total_unchanged(): void
    {
        $this->company();
        $from = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 10000]);
        $to = Account::query()->create(['name' => 'bKash', 'type' => 'mobile_banking', 'opening_balance' => 2000]);
        $user = $this->user();

        $totalBefore = (float) $from->current_balance + (float) $to->current_balance;

        $transfer = FundTransfer::query()->create([
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 3000,
            'requested_by' => $user->getKey(),
        ]);

        app(FundTransferService::class)->approve($transfer, $user);

        $this->assertSame('7000.00', $from->fresh()->current_balance);
        $this->assertSame('5000.00', $to->fresh()->current_balance);
        $this->assertSame($totalBefore, (float) $from->fresh()->current_balance + (float) $to->fresh()->current_balance);
    }

    public function test_funding_a_purchase_beyond_its_total_is_rejected(): void
    {
        $this->company();
        $supplier = Supplier::query()->create(['name' => 'Overfund Supplier']);
        $product = Product::query()->create(['name' => 'Overfund Product', 'sku' => 'OF-001', 'price' => 100, 'sale_price' => 100, 'stock' => 0]);
        $purchase = Purchase::query()->create(['supplier_id' => $supplier->getKey(), 'purchase_date' => now(), 'status' => 'received']);
        PurchaseItem::query()->create(['purchase_id' => $purchase->getKey(), 'product_id' => $product->getKey(), 'quantity' => 1, 'unit_cost' => 1000]);
        $purchase->refresh();

        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 50000]);
        $fundSource = FundSource::query()->create(['name' => 'Cash', 'type' => 'cash', 'account_id' => $account->getKey()]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'inventory_purchase',
            'amount' => (float) $purchase->total_amount + 500,
            'purchase_id' => $purchase->getKey(),
            'fund_source_id' => $fundSource->getKey(),
        ], $user);

        $this->expectException(ValidationException::class);
        $service->approve($voucher, $user);
    }

    public function test_capital_investment_credits_account_and_fund_source_balance(): void
    {
        $this->company();
        $account = Account::query()->create(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => 0]);
        $fundSource = FundSource::query()->create(['name' => 'Owner Capital', 'type' => 'owner_investment']);
        $user = $this->user();

        $voucher = app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_CREDIT,
            'transaction_type' => 'capital_investment',
            'amount' => 100000,
            'account_id' => $account->getKey(),
            'fund_source_id' => $fundSource->getKey(),
        ], $user);

        app(VoucherService::class)->verify($voucher, $user);
        app(VoucherService::class)->approve($voucher, $user);

        $this->assertSame('100000.00', $account->fresh()->current_balance);
        $this->assertSame(100000.0, $fundSource->fresh()->balance());
    }
}
