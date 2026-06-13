<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_csv_sample_can_be_downloaded(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('customers.import.sample'));

        $response->assertOk();
        $response->assertDownload('customers-import-sample.csv');
        $this->assertStringContainsString('name,phone,email', $response->streamedContent());
    }

    public function test_customer_csv_export_downloads_customers(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'Export Customer',
            'phone' => '+8801712345678',
            'email' => 'export@example.com',
            'customer_type' => 'retail',
            'customer_source' => 'facebook',
        ]);

        $response = $this->actingAs($user)->get(route('customers.export.csv'));

        $response->assertOk();
        $response->assertDownload('customers-export.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Export Customer', $content);
        $this->assertStringContainsString('export@example.com', $content);
        $this->assertStringContainsString('facebook', $content);
    }

    public function test_customer_csv_import_creates_and_updates_customers(): void
    {
        Storage::fake('local');

        Customer::query()->create([
            'name' => 'Old Customer',
            'phone' => '+8801700000000',
            'email' => 'existing@example.com',
            'customer_type' => 'regular',
        ]);

        $path = Storage::disk('local')->path('customers.csv');
        file_put_contents($path, implode(PHP_EOL, [
            implode(',', CustomerCsvService::HEADINGS),
            'New Customer,+8801711111111,new@example.com,Corporate Client,Trade Fair,500,yes,"Dhaka"',
            'Updated Customer,+8801700000000,existing@example.com,Dealer,WhatsApp Lead,750,no,"Chattogram"',
        ]));

        $result = app(CustomerCsvService::class)->import($path);

        $this->assertSame(['created' => 1, 'updated' => 1], $result);

        $this->assertDatabaseHas('customers', [
            'email' => 'new@example.com',
            'name' => 'New Customer',
            'customer_type' => 'Corporate Client',
            'customer_source' => 'Trade Fair',
            'opening_balance' => 500,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'existing@example.com',
            'name' => 'Updated Customer',
            'customer_type' => 'Dealer',
            'customer_source' => 'WhatsApp Lead',
            'opening_balance' => 750,
            'is_active' => false,
        ]);

        $this->assertArrayHasKey('Corporate Client', Customer::typeOptions());
        $this->assertArrayHasKey('Trade Fair', Customer::sourceOptions());
    }

    public function test_customer_csv_import_rejects_invalid_email(): void
    {
        Storage::fake('local');

        $path = Storage::disk('local')->path('customers-invalid.csv');
        file_put_contents($path, implode(PHP_EOL, [
            implode(',', CustomerCsvService::HEADINGS),
            'Invalid Customer,+8801711111111,invalid-email,regular,facebook,0,yes,Dhaka',
        ]));

        $this->expectException(ValidationException::class);

        app(CustomerCsvService::class)->import($path);
    }

    public function test_customer_csv_routes_require_sales_access(): void
    {
        $user = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('customers.export.csv'))->assertForbidden();
        $this->actingAs($user)->get(route('customers.import.sample'))->assertForbidden();
    }
}
