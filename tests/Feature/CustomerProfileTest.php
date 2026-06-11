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
}
