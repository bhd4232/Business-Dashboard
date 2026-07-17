<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected function company(string $slug, string $prefix): Company
    {
        return Company::query()->create([
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'invoice_prefix' => $prefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    public function test_lead_is_created_with_current_company_id(): void
    {
        $company = $this->company('lead-co', 'LC');
        app(CompanyContext::class)->set($company);

        $lead = Lead::query()->create([
            'name' => 'Rahim Uddin',
            'phone' => '01711111111',
            'source' => 'facebook',
        ]);

        $this->assertSame($company->getKey(), $lead->company_id);
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_leads_are_isolated_between_companies(): void
    {
        $first = $this->company('first-co', 'FC');
        $second = $this->company('second-co', 'SC');

        app(CompanyContext::class)->set($first);
        Lead::query()->create(['name' => 'First Lead', 'phone' => '01711111111']);

        app(CompanyContext::class)->set($second);
        Lead::query()->create(['name' => 'Second Lead', 'phone' => '01722222222']);

        $this->assertSame(['Second Lead'], Lead::query()->pluck('name')->all());

        app(CompanyContext::class)->set($first);
        $this->assertSame(['First Lead'], Lead::query()->pluck('name')->all());
    }

    public function test_lead_status_can_be_changed(): void
    {
        $company = $this->company('status-co', 'ST');
        app(CompanyContext::class)->set($company);

        $lead = Lead::query()->create(['name' => 'Status Lead', 'phone' => '01733333333']);
        $lead->update(['status' => 'contacted']);

        $this->assertSame('contacted', $lead->fresh()->status);
    }
}
