<?php

namespace Tests\Feature;

use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\FundSource;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use App\Services\VoucherService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

    protected function orderFor(Customer $customer, string $number, float $due = 1000, string $status = 'confirmed'): Order
    {
        return Order::withoutEvents(fn (): Order => Order::query()->create([
            'company_id' => $customer->company_id,
            'order_number' => $number,
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'order_date' => now()->toDateString(),
            'subtotal' => $due,
            'total_amount' => $due,
            'paid_amount' => 0,
            'due_amount' => $due,
            'status' => $status,
        ]));
    }

    public function test_credit_voucher_flow_pending_verified_approved_creates_customer_payment(): void
    {
        $this->company();
        $customer = Customer::query()->create(['name' => 'Voucher Customer', 'phone' => '01711111111']);
        $order = $this->orderFor($customer, 'INV-CREDIT-001');
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_CREDIT,
            'transaction_type' => 'customer_payment',
            'amount' => 500,
            'customer_id' => $customer->getKey(),
            'order_ids' => [$order->getKey()],
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
        $firstOrder = $this->orderFor($customer, 'INV-CREDIT-002', 600);
        $secondOrder = $this->orderFor($customer, 'INV-CREDIT-003', 400);
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);
        $user = $this->user();
        $service = app(VoucherService::class);

        $voucher = $service->submit([
            'type' => Voucher::TYPE_CREDIT,
            'transaction_type' => 'customer_payment',
            'amount' => 500,
            'customer_id' => $customer->getKey(),
            'order_ids' => [$firstOrder->getKey(), $secondOrder->getKey()],
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
        $this->assertSame(1500.0, (float) $customer->fresh()->current_balance);
        $this->assertEqualsCanonicalizing(
            [$firstOrder->getKey(), $secondOrder->getKey()],
            $voucher->orders()->pluck('orders.id')->all(),
        );
    }

    public function test_credit_voucher_rejects_invoices_from_different_customers(): void
    {
        $this->company();
        $firstCustomer = Customer::query()->create(['name' => 'First Customer', 'phone' => '01710000001']);
        $secondCustomer = Customer::query()->create(['name' => 'Second Customer', 'phone' => '01710000002']);
        $firstOrder = $this->orderFor($firstCustomer, 'INV-MIX-001');
        $secondOrder = $this->orderFor($secondCustomer, 'INV-MIX-002');
        $account = Account::query()->create(['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);

        $this->expectException(ValidationException::class);

        app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_CREDIT,
            'transaction_type' => 'customer_payment',
            'amount' => 500,
            'order_ids' => [$firstOrder->getKey(), $secondOrder->getKey()],
            'account_id' => $account->getKey(),
        ], $this->user());
    }

    public function test_credit_voucher_form_saves_multiple_invoices_for_one_customer(): void
    {
        $this->company();
        $customer = Customer::query()->create([
            'name' => 'Multi Invoice Customer',
            'phone' => '01712223344',
            'opening_balance' => 2000,
        ]);
        $firstOrder = $this->orderFor($customer, 'INV-FORM-001', 700);
        $secondOrder = $this->orderFor($customer, 'INV-FORM-002', 800);
        $account = Account::query()->create(['name' => 'Receiving Bank', 'type' => 'bank', 'opening_balance' => 0]);
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(CreateVoucher::class)
            ->fillForm([
                'type' => Voucher::TYPE_CREDIT,
                'amount' => 500,
                'account_id' => $account->getKey(),
                'confirmation_source' => 'manual',
                'transaction_id' => 'TXN-CR-001',
                'order_ids' => [$firstOrder->getKey(), $secondOrder->getKey()],
                'purpose' => 'Payment against two invoices',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $voucher = Voucher::query()->sole();

        $this->assertSame($customer->getKey(), $voucher->customer_id);
        $this->assertSame($account->getKey(), $voucher->account_id);
        $this->assertSame('TXN-CR-001', $voucher->transaction_id);
        $this->assertEqualsCanonicalizing(
            [$firstOrder->getKey(), $secondOrder->getKey()],
            $voucher->orders()->pluck('orders.id')->all(),
        );
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

    public function test_inventory_purchase_selector_shows_latest_five_incomplete_purchases_and_searches_older_ones(): void
    {
        $company = $this->company();
        $supplier = Supplier::query()->create(['name' => 'Voucher Purchase Supplier']);
        $purchases = collect(range(1, 7))->map(fn (int $sequence): Purchase => Purchase::withoutEvents(
            fn (): Purchase => Purchase::query()->create([
                'company_id' => $company->getKey(),
                'purchase_number' => 'PUR-SELECT-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier->getKey(),
                'purchase_date' => now()->toDateString(),
                'status' => 'draft',
            ]),
        ));
        $received = Purchase::withoutEvents(fn (): Purchase => Purchase::query()->create([
            'company_id' => $company->getKey(),
            'purchase_number' => 'PUR-SELECT-RECEIVED',
            'supplier_id' => $supplier->getKey(),
            'purchase_date' => now()->toDateString(),
            'status' => 'received',
        ]));

        Livewire::actingAs($this->user())
            ->test(CreateVoucher::class)
            ->fillForm([
                'type' => Voucher::TYPE_DEBIT,
                'transaction_type' => 'inventory_purchase',
            ])
            ->assertSeeInOrder(['Transaction Type', 'Purchase Number', 'Account', 'Amount'])
            ->assertDontSee('Payment Method')
            ->assertDontSee('Parties & Account')
            ->assertSchemaComponentVisible('purchase_id')
            ->assertSchemaComponentExists('purchase_id', 'form', function (Select $field) use ($purchases, $received): bool {
                $expectedLatestIds = $purchases
                    ->sortByDesc('id')
                    ->take(5)
                    ->pluck('id')
                    ->values()
                    ->all();
                $initialOptionIds = array_map('intval', array_keys($field->getOptions()));
                $olderSearchIds = array_map('intval', array_keys($field->getSearchResults('PUR-SELECT-001')));
                $receivedSearchIds = array_map('intval', array_keys($field->getSearchResults('PUR-SELECT-RECEIVED')));

                return $initialOptionIds === $expectedLatestIds
                    && $olderSearchIds === [$purchases->first()->getKey()]
                    && ! in_array($received->getKey(), $initialOptionIds, true)
                    && $receivedSearchIds === [];
            });
    }

    public function test_refund_form_requires_a_searchable_eligible_invoice_and_has_no_remarks_field(): void
    {
        $this->company();
        $customer = Customer::query()->create([
            'name' => 'Refund Customer',
            'phone' => '01715556677',
        ]);
        $invoice = $this->orderFor($customer, 'INV-REFUND-001', 500);
        $draftInvoice = $this->orderFor($customer, 'INV-REFUND-DRAFT', 500, 'draft');
        $account = Account::query()->create(['name' => 'Refund Bank', 'type' => 'bank', 'opening_balance' => 1000]);
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(CreateVoucher::class)
            ->fillForm([
                'type' => Voucher::TYPE_DEBIT,
                'transaction_type' => 'refund',
                'order_id' => $invoice->getKey(),
                'account_id' => $account->getKey(),
                'amount' => 200,
                'purpose' => 'Refund against invoice',
            ])
            ->assertSchemaComponentVisible('order_id')
            ->assertSchemaComponentDoesNotExist('remarks')
            ->assertSchemaComponentExists('purpose', 'form', fn (Textarea $field): bool => $field->getLabel() === 'Notes')
            ->assertSchemaComponentExists('order_id', 'form', function (Select $field) use ($invoice, $draftInvoice): bool {
                $optionIds = array_map('intval', array_keys($field->getOptions()));
                $phoneSearchIds = array_map('intval', array_keys($field->getSearchResults('01715556677')));

                return in_array($invoice->getKey(), $optionIds, true)
                    && ! in_array($draftInvoice->getKey(), $optionIds, true)
                    && in_array($invoice->getKey(), $phoneSearchIds, true)
                    && ! in_array($draftInvoice->getKey(), $phoneSearchIds, true);
            })
            ->call('create')
            ->assertHasNoFormErrors();

        $voucher = Voucher::query()->sole();
        $this->assertSame($invoice->getKey(), $voucher->order_id);
        $this->assertSame($customer->getKey(), $voucher->customer_id);

        app(VoucherService::class)->approve($voucher, $user);

        $this->assertSame('800.00', $account->fresh()->current_balance);
    }

    public function test_refund_voucher_rejects_a_missing_invoice(): void
    {
        $this->company();
        $account = Account::query()->create(['name' => 'Refund Cash', 'type' => 'cash', 'opening_balance' => 1000]);

        $this->expectException(ValidationException::class);

        app(VoucherService::class)->submit([
            'type' => Voucher::TYPE_DEBIT,
            'transaction_type' => 'refund',
            'account_id' => $account->getKey(),
            'amount' => 100,
        ], $this->user());
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
