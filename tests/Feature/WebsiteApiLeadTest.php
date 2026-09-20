<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\Lead;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteApiLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_lead_sourced_from_the_website(): void
    {
        $company = Company::query()->create([
            'name' => 'Tasneem Knitting',
            'slug' => 'tasneem-knitting',
            'invoice_prefix' => 'TK',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        [, $token] = CompanyApiKey::generate($company);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Karim Traders',
            'phone' => '01811223344',
            'interest' => 'Bulk cotton yarn order',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertCreated()
            ->assertJsonPath('data.status', 'new');

        app(CompanyContext::class)->set($company);
        $lead = Lead::query()->findOrFail($response->json('data.id'));

        $this->assertSame('website', $lead->source);
        $this->assertSame('Karim Traders', $lead->name);
    }

    public function test_name_and_phone_are_required(): void
    {
        $company = Company::query()->create([
            'name' => 'Tasneem Knitting', 'slug' => 'tasneem-knitting', 'invoice_prefix' => 'TK',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        [, $token] = CompanyApiKey::generate($company);

        $this->postJson('/api/v1/leads', [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }
}
