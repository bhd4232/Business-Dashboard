<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_email_must_be_valid_when_present(): void
    {
        $this->expectException(ValidationException::class);

        Customer::query()->create([
            'name' => 'Invalid Email Customer',
            'email' => 'customer@domain',
            'customer_type' => 'regular',
        ]);
    }

    public function test_supplier_email_must_be_valid_when_present(): void
    {
        $this->expectException(ValidationException::class);

        Supplier::query()->create([
            'name' => 'Invalid Email Supplier',
            'email' => 'supplier@invalid@demo',
        ]);
    }

    public function test_user_email_is_required_and_valid(): void
    {
        $this->expectException(ValidationException::class);

        User::query()->create([
            'name' => 'Invalid Email User',
            'email' => 'wrong-email',
            'password' => 'password',
            'role' => 'sales_staff',
            'is_active' => true,
        ]);
    }

    public function test_customer_and_supplier_email_can_be_empty(): void
    {
        $customer = Customer::query()->create([
            'name' => 'No Email Customer',
            'email' => '',
            'customer_type' => 'regular',
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'No Email Supplier',
            'email' => '',
        ]);

        $this->assertNull($customer->refresh()->email);
        $this->assertNull($supplier->refresh()->email);
    }

    public function test_email_accepts_domain_extension_format(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Valid Email Customer',
            'email' => 'test@domain.com',
            'customer_type' => 'regular',
        ]);

        $this->assertSame('test@domain.com', $customer->refresh()->email);
    }
}
