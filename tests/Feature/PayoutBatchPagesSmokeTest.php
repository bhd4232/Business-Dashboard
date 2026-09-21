<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutBatchPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_pages_render_for_super_admin(): void
    {
        $company = Company::query()->create([
            'name' => 'Smoke Co', 'slug' => 'smoke-co', 'invoice_prefix' => 'SMK',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get('/admin/investments/payout-batches')
            ->assertOk()
            ->assertSee('Payout Batches');

        $this->get('/admin/investments/company-payout-settings')
            ->assertOk()
            ->assertSee('Payout Bank Account');

        $batch = PayoutBatch::query()->create([
            'company_id' => $company->id, 'batch_number' => 'PB-TEST-001',
            'method' => 'beftn_bank', 'status' => 'draft', 'generated_by' => $user->id,
        ]);
        $this->get("/admin/investments/payout-batches/{$batch->id}")->assertOk()->assertSee('PB-TEST-001');
    }

    public function test_payout_bank_account_page_is_hidden_from_a_non_super_admin(): void
    {
        $company = Company::query()->create([
            'name' => 'Smoke Co 2', 'slug' => 'smoke-co-2', 'invoice_prefix' => 'SM2',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);

        $this->actingAs($accountant)
            ->withSession(['current_company_id' => $company->id])
            ->get('/admin/investments/company-payout-settings')
            ->assertForbidden();
    }
}
