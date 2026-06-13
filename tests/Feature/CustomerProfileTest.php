<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_fields_can_be_saved(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Walk In Customer',
            'phone' => '01700000000',
            'email' => 'customer@example.com',
            'address' => 'Dhaka',
            'customer_type' => 'retail',
            'customer_source' => 'facebook',
        ]);

        $this->assertSame('retail', $customer->refresh()->customer_type);
        $this->assertSame('facebook', $customer->customer_source);
        $this->assertDatabaseHas('customers', [
            'phone' => '01700000000',
            'customer_type' => 'retail',
            'customer_source' => 'facebook',
        ]);
    }

    public function test_customer_source_options_include_custom_sources(): void
    {
        Customer::query()->create([
            'name' => 'TikTok Customer',
            'customer_type' => 'regular',
            'customer_source' => Customer::sourceKey('TikTok Shop'),
        ]);

        $this->assertSame('TikTok Shop', Customer::sourceKey('TikTok Shop'));
        $this->assertSame('Tiktok Shop', Customer::sourceLabel('tiktok_shop'));
        $this->assertSame('Facebook', Customer::sourceLabel('facebook'));
        $this->assertArrayHasKey('TikTok Shop', Customer::sourceOptions());
    }

    public function test_customer_type_options_include_custom_types(): void
    {
        Customer::query()->create([
            'name' => 'Corporate Customer',
            'customer_type' => Customer::typeKey('Corporate Client'),
        ]);

        $this->assertSame('Corporate Client', Customer::typeKey('Corporate Client'));
        $this->assertSame('Dealer Partner', Customer::typeLabel('dealer_partner'));
        $this->assertSame('Regular', Customer::typeLabel('regular'));
        $this->assertArrayHasKey('Corporate Client', Customer::typeOptions());
    }
}
