<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_csv_sample_can_be_downloaded(): void
    {
        $user = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('suppliers.import.sample'));

        $response->assertOk();
        $response->assertDownload('suppliers-import-sample.csv');
        $this->assertStringContainsString('name,company_name,phone,email', $response->streamedContent());
    }

    public function test_supplier_csv_export_downloads_suppliers(): void
    {
        $user = User::factory()->create([
            'role' => 'inventory_staff',
            'is_active' => true,
        ]);

        Supplier::query()->create([
            'name' => 'Export Supplier',
            'company_name' => 'Export Trading Co.',
            'phone' => '+8613800138000',
            'email' => 'supplier-export@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('suppliers.export.csv'));

        $response->assertOk();
        $response->assertDownload('suppliers-export.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Export Supplier', $content);
        $this->assertStringContainsString('Export Trading Co.', $content);
        $this->assertStringContainsString('supplier-export@example.com', $content);
    }

    public function test_supplier_csv_import_creates_and_updates_suppliers(): void
    {
        Storage::fake('local');

        Supplier::query()->create([
            'name' => 'Old Supplier',
            'company_name' => 'Old Trading Co.',
            'phone' => '+8613000000000',
            'email' => 'existing-supplier@example.com',
        ]);

        $path = Storage::disk('local')->path('suppliers.csv');
        file_put_contents($path, implode(PHP_EOL, [
            implode(',', SupplierCsvService::HEADINGS),
            'New Supplier,New Import Co.,+8613111111111,new-supplier@example.com,2500,yes,"Shenzhen, China"',
            'Updated Supplier,Updated Trading Co.,+8613000000000,existing-supplier@example.com,4000,no,"Guangzhou, China"',
        ]));

        $result = app(SupplierCsvService::class)->import($path);

        $this->assertSame(['created' => 1, 'updated' => 1], $result);

        $this->assertDatabaseHas('suppliers', [
            'email' => 'new-supplier@example.com',
            'name' => 'New Supplier',
            'company_name' => 'New Import Co.',
            'opening_balance' => 2500,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'email' => 'existing-supplier@example.com',
            'name' => 'Updated Supplier',
            'company_name' => 'Updated Trading Co.',
            'opening_balance' => 4000,
            'is_active' => false,
        ]);
    }

    public function test_supplier_csv_import_rejects_invalid_email(): void
    {
        Storage::fake('local');

        $path = Storage::disk('local')->path('suppliers-invalid.csv');
        file_put_contents($path, implode(PHP_EOL, [
            implode(',', SupplierCsvService::HEADINGS),
            'Invalid Supplier,Invalid Co.,+8613111111111,invalid-email,0,yes,Shenzhen',
        ]));

        $this->expectException(ValidationException::class);

        app(SupplierCsvService::class)->import($path);
    }

    public function test_supplier_csv_routes_require_purchasing_access(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('suppliers.export.csv'))->assertForbidden();
        $this->actingAs($user)->get(route('suppliers.import.sample'))->assertForbidden();
    }
}
