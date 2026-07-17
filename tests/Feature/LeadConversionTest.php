<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\CompanyContext;
use App\Services\Crm\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConversionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Conversion Co',
            'slug' => 'conversion-co',
            'invoice_prefix' => 'CV',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);
    }

    public function test_lead_converts_to_customer_and_stores_converted_customer_id(): void
    {
        $lead = Lead::query()->create([
            'name' => 'Convert Me',
            'phone' => '01744444444',
            'email' => 'convert@example.com',
            'source' => 'whatsapp',
        ]);

        $customer = app(LeadConversionService::class)->convertToCustomer($lead);

        $this->assertSame('Convert Me', $customer->name);
        $this->assertSame('whatsapp', $customer->customer_source);
        $this->assertSame($customer->getKey(), $lead->fresh()->converted_customer_id);
    }

    public function test_existing_customer_with_same_phone_is_reused(): void
    {
        $existing = Customer::query()->create([
            'name' => 'Already Here',
            'phone' => '01755555555',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $lead = Lead::query()->create(['name' => 'Duplicate Phone', 'phone' => '01755555555']);

        $customer = app(LeadConversionService::class)->convertToCustomer($lead);

        $this->assertSame($existing->getKey(), $customer->getKey());
        $this->assertSame(1, Customer::query()->where('phone', '01755555555')->count());
    }

    public function test_reconverting_a_converted_lead_returns_the_same_customer(): void
    {
        $lead = Lead::query()->create(['name' => 'Once Only', 'phone' => '01766666666']);

        $service = app(LeadConversionService::class);
        $first = $service->convertToCustomer($lead);
        $second = $service->convertToCustomer($lead->fresh());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, Customer::query()->count());
    }
}
