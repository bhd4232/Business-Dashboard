<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_a_cannot_see_company_bs_vouchers(): void
    {
        $context = app(CompanyContext::class);

        $companyA = Company::query()->create(['name' => 'A', 'slug' => 'a', 'invoice_prefix' => 'A', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        $context->set($companyA);
        $accountA = Account::query()->create(['name' => 'Cash A', 'type' => 'cash', 'opening_balance' => 10000]);
        $categoryA = ExpenseCategory::query()->create(['name' => 'Rent A', 'slug' => 'rent-a']);
        $userA = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $voucherA = app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'business_expense',
            'amount' => 1000,
            'expense_category_id' => $categoryA->getKey(),
            'account_id' => $accountA->getKey(),
        ], $userA);

        $companyB = Company::query()->create(['name' => 'B', 'slug' => 'b', 'invoice_prefix' => 'B', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        $context->set($companyB);

        $this->assertNull(Voucher::query()->find($voucherA->getKey()));
        $this->assertSame(0, Voucher::query()->count());

        $context->set($companyA);
        $this->assertNotNull(Voucher::query()->find($voucherA->getKey()));
    }
}
