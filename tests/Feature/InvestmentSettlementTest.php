<?php

namespace Tests\Feature;

use App\Filament\Resources\InvestmentProjects\Pages\CreateInvestmentProject;
use App\Filament\Resources\InvestmentProjects\Pages\ViewInvestmentProject;
use App\Filament\Resources\InvestmentProjects\RelationManagers\CostItemsRelationManager;
use App\Filament\Resources\InvestmentProjects\RelationManagers\InvestmentsRelationManager as ProjectInvestmentsRelationManager;
use App\Filament\Resources\InvestmentRecords\InvestmentRecordResource;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Investment;
use App\Models\InvestmentProject;
use App\Models\Investor;
use App\Models\InvestorSecurityInstrument;
use App\Models\ProjectCostItem;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\Investment\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class InvestmentSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Mudarabah Company',
            'slug' => 'mudarabah-company',
            'invoice_prefix' => 'MUD',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);
        $this->user = User::factory()->create(['role' => 'super_admin']);
        Auth::login($this->user);
    }

    public function test_configured_split_costs_payout_ratio_and_annualized_return_are_calculated(): void
    {
        [$project, $investorA, $investorB] = $this->profitableProject();

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertEquals(100000, $settlement->total_cost);
        $this->assertEquals(100000, $settlement->net_profit);
        $this->assertEquals(40000, $settlement->investor_pool_amount);
        $this->assertEquals(0, $settlement->channel_partner_amount);
        $this->assertEquals(60000, $settlement->company_net_amount);
        // Investor yield annualized on a 360-day year: (40000 / 100000) × (360 / 60) × 100.
        $this->assertEquals(240.00, $settlement->annualized_return_percent);
        // Investor pool ÷ (total capital ÷ 1 lakh) = 40000 ÷ 1.
        $this->assertEquals(40000.00, $settlement->rate_per_lac);
        $this->assertEquals(24000, $settlement->payouts()->where('investor_id', $investorA->id)->value('profit_share_amount'));
        $this->assertEquals(16000, $settlement->payouts()->where('investor_id', $investorB->id)->value('profit_share_amount'));
        $this->assertEquals(84000, $settlement->payouts()->where('investor_id', $investorA->id)->value('total_payout'));
        $this->assertSame('settled', $project->fresh()->status);
    }

    public function test_company_gap_fill_contribution_shares_the_pool_and_folds_into_company_net(): void
    {
        // Project-01 shape: ৳62 lakh target, ৳53 lakh from one investor,
        // ৳9 lakh contributed by the company to close the gap.
        $project = $this->project([
            'name' => 'Gap Fill Project',
            'company_contribution_amount' => 900000,
        ]);
        $investor = $this->investor('Gap Fill Investor', '01810000050');
        $this->investment($project, $investor, 5300000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Machine', 'amount' => 6455442]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 7000000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertEquals(544558, $settlement->net_profit);
        $this->assertEquals(217823.20, $settlement->investor_pool_amount);
        // Rate per lac over the ৳62 lakh capital base.
        $this->assertEquals(3513.28, $settlement->rate_per_lac);
        // The single investor gets a strict proportional slice (53/62 of the pool).
        $investorShare = round(217823.20 * (5300000 / 6200000), 2);
        $this->assertEquals($investorShare, $settlement->payouts()->where('investor_id', $investor->id)->value('profit_share_amount'));
        // Company net = its 50% base + the 9-lakh slice of the pool + rounding dust.
        $this->assertEquals(round(544558 - $investorShare - 0, 2), $settlement->company_net_amount);
        // Investors only see the pool line, so annualized uses pool ÷ base.
        $this->assertEquals(round((217823.20 / 6200000) * 6 * 100, 2), $settlement->annualized_return_percent);
    }

    public function test_shearing_machine_settlement_matches_the_signed_sheet_example(): void
    {
        $project = $this->project([
            'name' => 'Shearing Machine',
            'target_amount' => 5000000,
        ]);
        $investor = $this->investor('Shearing Machine Investor', '01810000010');
        $this->investment($project, $investor, 5000000);
        ProjectCostItem::query()->create([
            'project_id' => $project->id,
            'category' => 'landed_cost',
            'label' => 'Machine landed cost',
            'amount' => 6199942,
        ]);
        ProjectCostItem::query()->create([
            'project_id' => $project->id,
            'category' => 'local_expense',
            'label' => 'Local delivery and handling',
            'amount' => 255500,
        ]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 7000000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertEquals(6199942, $project->totalLandedCost());
        $this->assertEquals(255500, $project->totalLocalExpense());
        $this->assertEquals(6455442, $settlement->total_cost);
        $this->assertEquals(544558, $settlement->net_profit);
        $this->assertEquals(217823.20, $settlement->investor_pool_amount);
        $this->assertEquals(0, $settlement->channel_partner_amount);
        $this->assertEquals(326734.80, $settlement->company_net_amount);
        $this->assertEquals(5217823.20, $settlement->payouts()->where('investor_id', $investor->id)->value('total_payout'));
    }

    public function test_channel_share_is_paid_for_referred_capital_and_unallocated_share_goes_to_company(): void
    {
        $partner = $this->investor('Channel Partner', '01710000000');
        $referred = $this->investor('Referred Investor', '01710000001', $partner);
        $direct = $this->investor('Direct Investor', '01710000002');
        $project = $this->project();
        $this->investment($project, $referred, 60000);
        $this->investment($project, $direct, 40000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Direct cost', 'amount' => 100000]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertEquals(6000, $settlement->channel_partner_amount);
        $this->assertEquals(54000, $settlement->company_net_amount);
        $this->assertEquals(6000, $settlement->channelPartnerPayouts()->where('investor_id', $partner->id)->value('amount'));
    }

    public function test_custom_project_percentages_are_used_instead_of_hardcoded_defaults(): void
    {
        $project = $this->project(['investor_share_percent' => 50, 'channel_partner_share_percent' => 5, 'company_share_percent' => 45]);
        $investor = $this->investor('Investor', '01720000000');
        $this->investment($project, $investor, 100000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Cost', 'amount' => 100000]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertEquals(50000, $settlement->investor_pool_amount);
        $this->assertEquals(0, $settlement->channel_partner_amount);
        $this->assertEquals(50000, $settlement->company_net_amount);
    }

    public function test_project_percentages_must_total_one_hundred(): void
    {
        $this->expectException(ValidationException::class);
        $this->project(['investor_share_percent' => 40, 'channel_partner_share_percent' => 10, 'company_share_percent' => 40]);
    }

    public function test_project_form_rejects_profit_split_that_does_not_total_one_hundred(): void
    {
        Livewire::test(CreateInvestmentProject::class)
            ->fillForm([
                'name' => 'Invalid Split Project',
                'duration_type' => 'custom_days',
                'trade_cycle_days' => 60,
                'start_date' => now()->toDateString(),
                'status' => 'open',
                'investor_share_percent' => 40,
                'channel_partner_share_percent' => 10,
                'company_share_percent' => 40,
            ])
            ->call('create')
            ->assertHasFormErrors(['company_share_percent']);

        $this->assertDatabaseMissing('investment_projects', ['name' => 'Invalid Split Project']);
    }

    public function test_settlement_is_blocked_when_a_cost_item_has_no_purchase_or_receipt(): void
    {
        $project = $this->project();
        $investor = $this->investor('Proof Investor', '01880000001');
        $this->investment($project, $investor, 100000);
        $cost = ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Undocumented cost', 'amount' => 50000]);

        try {
            app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);
            $this->fail('Settlement went through without cost proof.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Undocumented cost', $e->getMessage());
        }

        // Attaching a receipt clears the block.
        $cost->documents()->create(['company_id' => $project->company_id, 'category' => 'cost_receipt', 'file_path' => app(CompanyStorageService::class)->putPrivate($this->company, 'investment-documents', 'receipt.pdf', 'receipt')]);
        $settlement = app(SettlementService::class)->calculateAndSettle($project->fresh(), 200000, $this->user->id);
        $this->assertEquals(150000, $settlement->net_profit);
    }

    public function test_settlement_proceeds_when_unproven_costs_are_acknowledged(): void
    {
        $project = $this->project();
        $investor = $this->investor('Ack Investor', '01880000002');
        $this->investment($project, $investor, 100000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Cost', 'amount' => 50000]);

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertEquals(150000, $settlement->net_profit);
    }

    public function test_investment_documents_are_company_scoped_and_downloadable(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $project = $this->project(['status' => 'open']);
        $path = app(CompanyStorageService::class)->putPrivate($this->company, 'investment-documents', 'sheet.pdf', 'settlement sheet');
        $document = $project->documents()->create(['company_id' => $this->company->id, 'category' => 'settlement_sheet', 'file_path' => $path]);

        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('investment-documents.download', $document))
            ->assertOk();

        $other = \App\Models\Company::query()->create(['name' => 'Doc Other', 'slug' => 'doc-other', 'invoice_prefix' => 'DO', 'is_active' => true]);
        $this->withSession(['current_company_id' => $other->id])
            ->get(route('investment-documents.download', $document))
            ->assertNotFound();
    }

    public function test_settlement_cannot_be_created_twice(): void
    {
        [$project] = $this->profitableProject();
        app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->expectException(RuntimeException::class);
        app(SettlementService::class)->calculateAndSettle($project->fresh(), 200000, $this->user->id, acknowledgeUnprovenCosts: true);
    }

    public function test_negative_profit_without_a_loss_outcome_is_blocked(): void
    {
        [$project] = $this->losingProject();

        $this->expectException(RuntimeException::class);
        app(SettlementService::class)->calculateAndSettle($project, 100000, $this->user->id, acknowledgeUnprovenCosts: true);
    }

    public function test_negative_profit_requires_a_loss_reason(): void
    {
        [$project] = $this->losingProject();

        $this->expectException(RuntimeException::class);
        app(SettlementService::class)->calculateAndSettle($project, 100000, $this->user->id, acknowledgeUnprovenCosts: true, outcome: 'loss_investor_borne');
    }

    public function test_loss_borne_by_investors_erodes_capital_in_proportion(): void
    {
        [$project, $investorA, $investorB] = $this->losingProject(); // A 60k, B 40k, cost 150k

        $settlement = app(SettlementService::class)->calculateAndSettle(
            $project, 100000, $this->user->id,
            acknowledgeUnprovenCosts: true, outcome: 'loss_investor_borne', lossReason: 'Shipment sank — force majeure',
        );

        $this->assertEquals(-50000, $settlement->net_profit);
        $this->assertSame('loss_investor_borne', $settlement->outcome);
        // 50k loss over 100k capital: A bears 30k, B bears 20k.
        $this->assertEquals(-30000, $settlement->payouts()->where('investor_id', $investorA->id)->value('profit_share_amount'));
        $this->assertEquals(30000, $settlement->payouts()->where('investor_id', $investorA->id)->value('total_payout'));
        $this->assertEquals(20000, $settlement->payouts()->where('investor_id', $investorB->id)->value('total_payout'));
        $this->assertEquals(0, $settlement->channel_partner_amount);
        $this->assertEquals(0, $settlement->company_net_amount); // no company contribution
    }

    public function test_loss_borne_by_company_returns_full_principal_to_investors(): void
    {
        [$project, $investorA, $investorB] = $this->losingProject();

        $settlement = app(SettlementService::class)->calculateAndSettle(
            $project, 100000, $this->user->id,
            acknowledgeUnprovenCosts: true, outcome: 'loss_manager_borne', lossReason: 'Manager negligence',
        );

        $this->assertEquals(60000, $settlement->payouts()->where('investor_id', $investorA->id)->value('total_payout'));
        $this->assertEquals(40000, $settlement->payouts()->where('investor_id', $investorB->id)->value('total_payout'));
        $this->assertEquals(0, $settlement->payouts()->where('investor_id', $investorA->id)->value('profit_share_amount'));
        $this->assertEquals(-50000, $settlement->company_net_amount); // company eats the whole loss
    }

    public function test_settlement_figures_are_immutable(): void
    {
        [$project] = $this->profitableProject();
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->expectException(ValidationException::class);
        $settlement->update(['net_profit' => 1]);
    }

    public function test_settlement_is_created_as_a_draft_and_confirmed_by_super_admin(): void
    {
        [$project] = $this->profitableProject();
        $service = app(SettlementService::class);
        $settlement = $service->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertSame('draft', $settlement->status);

        $service->confirmSettlement($settlement, $this->user->id);
        $this->assertSame('confirmed', $settlement->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement_confirmed', 'auditable_id' => $settlement->id]);
    }

    public function test_settlement_status_cannot_skip_from_draft_to_paid_out(): void
    {
        [$project] = $this->profitableProject();
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->expectException(ValidationException::class);
        $settlement->update(['status' => 'paid_out']);
    }

    public function test_void_deletes_the_settlement_and_reopens_the_project(): void
    {
        [$project, $investorA] = $this->profitableProject();
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);
        $payoutId = $settlement->payouts()->first()->id;

        app(SettlementService::class)->voidSettlement($settlement, 'Wrong selling amount entered', $this->user->id);

        $this->assertDatabaseMissing('project_settlements', ['id' => $settlement->id]);
        $this->assertDatabaseMissing('settlement_payouts', ['id' => $payoutId]);
        $this->assertSame('closed', $project->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement_voided']);

        // The project can be settled again.
        $fresh = app(SettlementService::class)->calculateAndSettle($project->fresh(), 210000, $this->user->id, acknowledgeUnprovenCosts: true);
        $this->assertSame('draft', $fresh->status);
    }

    public function test_void_is_blocked_once_a_payout_has_been_paid(): void
    {
        [$project] = $this->profitableProject();
        $service = app(SettlementService::class);
        $settlement = $service->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);
        $service->confirmSettlement($settlement, $this->user->id);
        $settlement->payouts()->first()->update(['payment_status' => 'paid', 'paid_at' => now()->toDateString()]);

        $this->expectException(RuntimeException::class);
        $service->voidSettlement($settlement->fresh(), 'Too late', $this->user->id);
    }

    public function test_project_supports_only_one_channel_partner(): void
    {
        $firstPartner = $this->investor('First Partner', '01740000000');
        $secondPartner = $this->investor('Second Partner', '01740000001');
        $firstInvestor = $this->investor('First Investor', '01740000002', $firstPartner);
        $secondInvestor = $this->investor('Second Investor', '01740000003', $secondPartner);
        $project = $this->project(['status' => 'open']);
        $this->investment($project, $firstInvestor, 50000);

        $this->expectException(ValidationException::class);
        $this->investment($project, $secondInvestor, 50000);
    }

    public function test_channel_partner_change_requires_super_admin_permission_and_reason_and_is_audited(): void
    {
        $firstPartner = $this->investor('First Partner', '01750000000');
        $secondPartner = $this->investor('Second Partner', '01750000001');
        $investor = $this->investor('Investor', '01750000002', $firstPartner);
        $manager = User::factory()->create(['role' => 'manager']);
        Auth::login($manager);

        try {
            $investor->update(['channel_partner_id' => $secondPartner->id, 'channel_partner_change_reason' => 'Requested']);
            $this->fail('Manager changed an assigned channel partner.');
        } catch (ValidationException) {
            $this->assertSame($firstPartner->id, $investor->fresh()->channel_partner_id);
        }

        Auth::login($this->user);
        $investor->update(['channel_partner_id' => $secondPartner->id, 'channel_partner_change_reason' => 'Contract amended']);

        $audit = AuditLog::query()->where('auditable_type', Investor::class)->where('auditable_id', $investor->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('Contract amended', $audit->new_values['channel_partner_change_reason']);
    }

    public function test_investment_projects_are_company_isolated(): void
    {
        $this->project(['name' => 'First Company Project']);
        $other = Company::query()->create(['name' => 'Other Company', 'slug' => 'other-company', 'invoice_prefix' => 'OTH', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        app(CompanyContext::class)->set($other);
        $this->project(['name' => 'Other Company Project', 'company_id' => $other->id]);

        $this->assertSame(['Other Company Project'], InvestmentProject::query()->pluck('name')->all());
    }

    public function test_settlement_writes_a_dedicated_audit_event(): void
    {
        [$project] = $this->profitableProject();
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'project_settled', 'auditable_type' => $settlement::class, 'auditable_id' => $settlement->id, 'user_id' => $this->user->id]);
    }

    public function test_filament_investment_pages_render(): void
    {
        $project = $this->project(['status' => 'open', 'target_amount' => 100000]);
        $investor = $this->investor('Page Investor', '01810000020');
        $investment = $this->investment($project, $investor, 60000);

        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/admin/investments/investment-projects')
            ->assertOk()
            ->assertSee('Investment Projects')
            ->assertSee('60%');

        $this->get('/admin/investments/investors')->assertOk();
        $this->get('/admin/investments/project-settlements')->assertOk();
        $this->get('/admin/investments/investment-projects/create')
            ->assertOk()
            ->assertSee('Help for Project Name')
            ->assertSee('Help for Investor Share')
            ->assertSee('Profit split is valid');
        $this->get("/admin/investments/investment-projects/{$project->id}")
            ->assertOk()
            ->assertSee('Total Landed Cost')
            ->assertSee('Total Local Expense');
        $this->get(InvestmentRecordResource::getUrl('view', ['record' => $investment]))
            ->assertOk()
            ->assertSee('View Investment')
            ->assertSee('Page Investor');
    }

    public function test_project_relation_managers_render_cost_groups_and_investment_security_access(): void
    {
        $project = $this->project(['status' => 'open']);
        $investor = $this->investor('Relation Manager Investor', '01810000030');
        $this->investment($project, $investor, 60000);
        ProjectCostItem::query()->create([
            'project_id' => $project->id,
            'category' => 'landed_cost',
            'label' => 'Machine',
            'amount' => 90000,
        ]);
        ProjectCostItem::query()->create([
            'project_id' => $project->id,
            'category' => 'local_expense',
            'label' => 'Delivery',
            'amount' => 10000,
        ]);

        Livewire::test(CostItemsRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => ViewInvestmentProject::class,
        ])
            ->assertStatus(200)
            ->assertSee('Landed Cost')
            ->assertSee('Local Expense');

        Livewire::test(ProjectInvestmentsRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => ViewInvestmentProject::class,
        ])
            ->assertStatus(200)
            ->assertSee('Relation Manager Investor')
            ->assertSee('Security')
            ->assertSee('View');
    }

    public function test_private_contract_download_is_authenticated_and_company_scoped(): void
    {
        Storage::fake('local');
        $project = $this->project(['status' => 'open']);
        $investor = $this->investor('Contract Investor', '01890000001');
        $investment = $this->investment($project, $investor, 50000);
        $path = app(CompanyStorageService::class)->putPrivate($this->company, 'investor-contracts', 'signed.pdf', 'signed-contract');
        $instrument = InvestorSecurityInstrument::query()->create(['investment_id' => $investment->id, 'contract_document_path' => $path]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get(route('investor-security-instruments.contract', $instrument))
            ->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));

        $other = Company::query()->create(['name' => 'Other Company', 'slug' => 'contract-other', 'invoice_prefix' => 'CO', 'is_active' => true]);
        $this->withSession(['current_company_id' => $other->id])
            ->get(route('investor-security-instruments.contract', $instrument))
            ->assertNotFound();
    }

    public function test_investor_payout_report_and_project_register_render(): void
    {
        [$project, $investorA] = $this->profitableProject();
        $investorA->update(['guardian_name' => 'Guardian A', 'nominee_name' => 'Nominee A', 'display_name' => 'Alpha']);
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id, acknowledgeUnprovenCosts: true);
        $payout = $settlement->payouts()->where('investor_id', $investorA->id)->first();
        $payout->update(['payment_status' => 'paid', 'paid_at' => now()->toDateString(), 'recipient_bank_name' => 'Test Bank', 'recipient_account_number' => '123456']);

        $this->actingAs($this->user)->withSession(['current_company_id' => $this->company->id]);

        $this->get(route('investments.reports.investor-payout', $payout))
            ->assertOk()
            ->assertSee('বিনিয়োগ মুনাফা রিপোর্ট')
            ->assertSee('Alpha')          // pseudonym used on the shared report
            ->assertDontSee('Investor A')  // real name withheld
            ->assertSee('Test Bank')
            ->assertSee('Paid');

        $this->get(route('investments.reports.project-register', $project))
            ->assertOk()
            ->assertSee('Investor A')   // register keeps the legal name
            ->assertSee('Guardian A')
            ->assertSee('Nominee A');

        $other = Company::query()->create(['name' => 'Report Other', 'slug' => 'report-other', 'invoice_prefix' => 'RO', 'is_active' => true]);
        $this->withSession(['current_company_id' => $other->id])
            ->get(route('investments.reports.project-register', $project))
            ->assertNotFound();
    }

    private function profitableProject(): array
    {
        $project = $this->project();
        $investorA = $this->investor('Investor A', '01810000001');
        $investorB = $this->investor('Investor B', '01810000002');
        $this->investment($project, $investorA, 60000);
        $this->investment($project, $investorB, 40000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Machine', 'amount' => 90000]);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'local_expense', 'label' => 'Delivery', 'amount' => 10000]);

        return [$project, $investorA, $investorB];
    }

    /** A ৳100k capital / ৳150k cost project — settled at revenue 100k it loses 50k. */
    private function losingProject(): array
    {
        $project = $this->project();
        $investorA = $this->investor('Loss Investor A', '01820000001');
        $investorB = $this->investor('Loss Investor B', '01820000002');
        $this->investment($project, $investorA, 60000);
        $this->investment($project, $investorB, 40000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Machine', 'amount' => 150000]);

        return [$project, $investorA, $investorB];
    }

    private function project(array $overrides = []): InvestmentProject
    {
        return InvestmentProject::query()->create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Test Project',
            'duration_type' => 'custom_days',
            'trade_cycle_days' => 60,
            'start_date' => now()->toDateString(),
            'investor_share_percent' => 40,
            'channel_partner_share_percent' => 10,
            'company_share_percent' => 50,
            'status' => 'closed',
        ], $overrides));
    }

    private function investor(string $name, string $phone, ?Investor $partner = null): Investor
    {
        return Investor::query()->create(['company_id' => $this->company->id, 'name' => $name, 'phone' => $phone, 'channel_partner_id' => $partner?->id]);
    }

    private function investment(InvestmentProject $project, Investor $investor, float $amount): Investment
    {
        return Investment::query()->create(['company_id' => $project->company_id, 'project_id' => $project->id, 'investor_id' => $investor->id, 'amount' => $amount, 'payment_method' => 'bank', 'invested_at' => now()->toDateString(), 'received_by' => $this->user->id]);
    }
}
