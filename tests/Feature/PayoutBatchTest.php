<?php

namespace Tests\Feature;

use App\Models\ChannelPartnerPayout;
use App\Models\Company;
use App\Models\Investment;
use App\Models\InvestmentProject;
use App\Models\Investor;
use App\Models\ProjectCostItem;
use App\Models\ProjectSettlement;
use App\Models\SettlementPayout;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\Investment\SettlementService;
use App\Services\Payouts\PayoutBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

class PayoutBatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private PayoutBatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Payout Test Co',
            'slug' => 'payout-test-co',
            'invoice_prefix' => 'PTC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);
        $this->user = User::factory()->create(['role' => 'super_admin']);
        Auth::login($this->user);
        $this->service = app(PayoutBatchService::class);
    }

    public function test_batch_only_includes_pending_payouts_with_complete_bank_details(): void
    {
        [$settlement, $payoutA, $payoutB] = $this->settledProjectWithTwoInvestors();

        $this->fillBankDetails($payoutA);
        // $payoutB deliberately left without bank details — must be skipped.

        $batch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);

        $this->assertSame(1, $batch->total_items);
        $this->assertEquals($payoutA->fresh()->total_payout, $batch->total_amount);
        $this->assertSame('draft', $batch->status);
        $this->assertSame('SettlementPayout', class_basename($batch->items->first()->payable_type));
    }

    public function test_a_payout_already_in_an_active_batch_is_not_batched_again(): void
    {
        [$settlement, $payoutA] = $this->settledProjectWithTwoInvestors();
        $this->fillBankDetails($payoutA);

        $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);

        $this->expectException(RuntimeException::class);
        // The other investor has no bank details, and payoutA is already batched — nothing left to batch.
        $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);
    }

    public function test_throws_when_nothing_is_eligible(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);
    }

    public function test_generate_export_file_requires_an_approved_batch(): void
    {
        [$settlement, $payoutA] = $this->settledProjectWithTwoInvestors();
        $this->fillBankDetails($payoutA);
        $batch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);

        $this->expectException(RuntimeException::class);
        $this->service->generateExportFile($batch);
    }

    public function test_full_lifecycle_from_draft_to_completed_marks_the_underlying_payout_paid(): void
    {
        [$settlement, $payoutA] = $this->settledProjectWithTwoInvestors();
        $this->fillBankDetails($payoutA);

        $batch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);
        $this->assertTrue($payoutA->fresh()->hasActivePayoutItem());

        $batch = $this->service->approve($batch, $this->user);
        $this->assertSame('approved', $batch->status);

        $batch = $this->service->generateExportFile($batch);
        $this->assertSame('file_generated', $batch->status);
        $this->assertNotNull($batch->export_file_path);
        $this->assertSame('in_batch', $batch->items->first()->fresh()->status);

        $batch = $this->service->markSubmitted($batch, 'BANK-REF-001');
        $this->assertSame('submitted_to_bank', $batch->status);
        $this->assertSame('BANK-REF-001', $batch->bank_batch_reference);

        $item = $batch->items->first();
        $this->service->reconcileItem($item, 'paid', 'UTR-123');

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('paid', $payoutA->fresh()->payment_status);
        $this->assertSame('UTR-123', $payoutA->fresh()->payment_reference);
        $this->assertFalse($payoutA->fresh()->hasActivePayoutItem());
    }

    public function test_reconciling_a_failed_item_leaves_the_underlying_payout_pending_for_correction(): void
    {
        [$settlement, $payoutA] = $this->settledProjectWithTwoInvestors();
        $this->fillBankDetails($payoutA);

        $batch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);
        $batch = $this->service->approve($batch, $this->user);
        $batch = $this->service->generateExportFile($batch);
        $batch = $this->service->markSubmitted($batch, null);

        $item = $batch->items->first();
        $this->service->reconcileItem($item, 'failed', null, 'Account closed');

        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertSame('pending', $payoutA->fresh()->payment_status);
        $this->assertSame('failed', $item->fresh()->status);
        $this->assertSame('Account closed', $item->fresh()->failure_reason);
    }

    public function test_cancelling_a_draft_batch_releases_items_back_to_the_pending_pool(): void
    {
        [$settlement, $payoutA] = $this->settledProjectWithTwoInvestors();
        $this->fillBankDetails($payoutA);

        $batch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);
        $this->service->cancel($batch);

        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->assertFalse($payoutA->fresh()->hasActivePayoutItem());

        // Now that the item is released, it can be batched again.
        $newBatch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);
        $this->assertSame(1, $newBatch->total_items);
    }

    public function test_channel_partner_payouts_are_included_when_they_have_bank_details(): void
    {
        $project = InvestmentProject::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Channel Partner Project',
            'duration_type' => 'custom_days',
            'trade_cycle_days' => 60,
            'start_date' => now()->toDateString(),
            'investor_share_percent' => 40,
            'channel_partner_share_percent' => 10,
            'company_share_percent' => 50,
            'status' => 'closed',
        ]);
        $partner = Investor::query()->create(['company_id' => $this->company->id, 'name' => 'Partner', 'phone' => '01810000099']);
        $investor = Investor::query()->create(['company_id' => $this->company->id, 'name' => 'Referred Investor', 'phone' => '01810000098', 'channel_partner_id' => $partner->id]);
        Investment::query()->create(['company_id' => $this->company->id, 'project_id' => $project->id, 'investor_id' => $investor->id, 'amount' => 100000, 'payment_method' => 'bank', 'invested_at' => now()->toDateString()]);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Cost', 'amount' => 100000]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);
        $channelPayout = $settlement->channelPartnerPayouts()->first();
        $this->assertNotNull($channelPayout);
        $this->assertSame('Partner', $channelPayout->recipient_name);

        $channelPayout->update([
            'recipient_bank_name' => 'Test Bank', 'recipient_branch' => 'Main',
            'recipient_routing_number' => '123456789', 'recipient_account_number' => '00112233',
        ]);
        $settlement->payouts()->update([
            'recipient_bank_name' => 'Test Bank', 'recipient_branch' => 'Main',
            'recipient_routing_number' => '123456789', 'recipient_account_number' => '00112244',
        ]);

        $batch = $this->service->createBatchFromPendingInvestorPayouts($this->company, $this->user);

        $this->assertSame(2, $batch->total_items);
        $this->assertTrue($batch->items->pluck('payable_type')->contains(ChannelPartnerPayout::class));
    }

    /** @return array{0: ProjectSettlement, 1: SettlementPayout, 2: SettlementPayout} */
    private function settledProjectWithTwoInvestors(): array
    {
        $project = InvestmentProject::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Project',
            'duration_type' => 'custom_days',
            'trade_cycle_days' => 60,
            'start_date' => now()->toDateString(),
            'investor_share_percent' => 40,
            'channel_partner_share_percent' => 10,
            'company_share_percent' => 50,
            'status' => 'closed',
        ]);
        $investorA = Investor::query()->create(['company_id' => $this->company->id, 'name' => 'Investor A', 'phone' => '01810000001']);
        $investorB = Investor::query()->create(['company_id' => $this->company->id, 'name' => 'Investor B', 'phone' => '01810000002']);
        Investment::query()->create(['company_id' => $this->company->id, 'project_id' => $project->id, 'investor_id' => $investorA->id, 'amount' => 60000, 'payment_method' => 'bank', 'invested_at' => now()->toDateString()]);
        Investment::query()->create(['company_id' => $this->company->id, 'project_id' => $project->id, 'investor_id' => $investorB->id, 'amount' => 40000, 'payment_method' => 'bank', 'invested_at' => now()->toDateString()]);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Machine', 'amount' => 90000]);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'local_expense', 'label' => 'Delivery', 'amount' => 10000]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

        $payoutA = $settlement->payouts()->where('investor_id', $investorA->id)->first();
        $payoutB = $settlement->payouts()->where('investor_id', $investorB->id)->first();

        return [$settlement, $payoutA, $payoutB];
    }

    private function fillBankDetails(SettlementPayout $payout): void
    {
        $payout->update([
            'recipient_bank_name' => 'Test Bank',
            'recipient_branch' => 'Main Branch',
            'recipient_routing_number' => '123456789',
            'recipient_account_number' => '00112233',
        ]);
    }
}
