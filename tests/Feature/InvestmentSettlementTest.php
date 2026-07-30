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

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

        $this->assertEquals(100000, $settlement->total_cost);
        $this->assertEquals(100000, $settlement->net_profit);
        $this->assertEquals(40000, $settlement->investor_pool_amount);
        $this->assertEquals(0, $settlement->channel_partner_amount);
        $this->assertEquals(60000, $settlement->company_net_amount);
        $this->assertEquals(608.33, $settlement->annualized_return_percent);
        $this->assertEquals(24000, $settlement->payouts()->where('investor_id', $investorA->id)->value('profit_share_amount'));
        $this->assertEquals(16000, $settlement->payouts()->where('investor_id', $investorB->id)->value('profit_share_amount'));
        $this->assertEquals(84000, $settlement->payouts()->where('investor_id', $investorA->id)->value('total_payout'));
        $this->assertSame('settled', $project->fresh()->status);
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

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 7000000, $this->user->id);

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

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

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

        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

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

    public function test_settlement_cannot_be_created_twice(): void
    {
        [$project] = $this->profitableProject();
        app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

        $this->expectException(RuntimeException::class);
        app(SettlementService::class)->calculateAndSettle($project->fresh(), 200000, $this->user->id);
    }

    public function test_negative_profit_is_blocked(): void
    {
        $project = $this->project();
        $investor = $this->investor('Investor', '01730000000');
        $this->investment($project, $investor, 100000);
        ProjectCostItem::query()->create(['project_id' => $project->id, 'category' => 'landed_cost', 'label' => 'Cost', 'amount' => 150000]);

        $this->expectException(RuntimeException::class);
        app(SettlementService::class)->calculateAndSettle($project, 100000, $this->user->id);
    }

    public function test_confirmed_settlement_amounts_are_immutable(): void
    {
        [$project] = $this->profitableProject();
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

        $this->expectException(ValidationException::class);
        $settlement->update(['net_profit' => 1]);
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
        $settlement = app(SettlementService::class)->calculateAndSettle($project, 200000, $this->user->id);

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
