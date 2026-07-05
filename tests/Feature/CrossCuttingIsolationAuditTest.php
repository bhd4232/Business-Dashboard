<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 1.10 cross-cutting isolation audit: exports, PDF downloads, and
 * report exports must never leak another company's records to a staff
 * user whose session is bound to a single company.
 */
class CrossCuttingIsolationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_csv_export_only_contains_current_company_products(): void
    {
        [$garments, $solar] = $this->createTwoCompanies();

        $this->createProduct($garments, 'Garments Cutter', 'ISO-GM-001');
        $this->createProduct($solar, 'Solar Panel', 'ISO-SOL-001');

        $user = $this->createStaff($garments, 'inventory_staff');

        $response = $this->actingAs($user)->get(route('products.export.csv'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('ISO-GM-001', $content);
        $this->assertStringNotContainsString('ISO-SOL-001', $content);
        $this->assertStringNotContainsString('Solar Panel', $content);
    }

    public function test_order_pdf_of_another_company_is_not_accessible(): void
    {
        [$garments, $solar] = $this->createTwoCompanies();

        $ownOrder = $this->createOrder($garments, 'GM-ISO-ORDER-1');
        $foreignOrder = $this->createOrder($solar, 'SOL-ISO-ORDER-1');

        $user = $this->createStaff($garments, 'manager');

        $this->actingAs($user)->get(route('orders.pdf', $ownOrder))->assertOk();
        $this->actingAs($user)->get(route('orders.pdf', $foreignOrder))->assertNotFound();
    }

    public function test_report_csv_export_is_scoped_to_current_company(): void
    {
        [$garments, $solar] = $this->createTwoCompanies();

        $this->createOrder($garments, 'GM-ISO-RPT-1');
        $this->createOrder($solar, 'SOL-ISO-RPT-1');

        $user = $this->createStaff($garments, 'manager');

        $response = $this->actingAs($user)->get(route('reports.export', ['type' => 'sales']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('GM-ISO-RPT-1', $content);
        $this->assertStringNotContainsString('SOL-ISO-RPT-1', $content);
    }

    public function test_customer_csv_export_only_contains_current_company_customers(): void
    {
        [$garments, $solar] = $this->createTwoCompanies();

        $this->createCustomer($garments, 'Garments Iso Customer', '01900000001');
        $this->createCustomer($solar, 'Solar Iso Customer', '01900000002');

        $user = $this->createStaff($garments, 'manager');

        $response = $this->actingAs($user)->get(route('customers.export.csv'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Garments Iso Customer', $content);
        $this->assertStringNotContainsString('Solar Iso Customer', $content);
    }

    protected function createTwoCompanies(): array
    {
        $garments = Company::query()->create([
            'name' => 'Garments Machinery Audit',
            'slug' => 'garments-audit',
            'invoice_prefix' => 'GM',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        $solar = Company::query()->create([
            'name' => 'Solar Items Audit',
            'slug' => 'solar-audit',
            'invoice_prefix' => 'SOL',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        return [$garments, $solar];
    }

    protected function createStaff(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $user->companies()->detach();
        $user->companies()->attach($company, [
            'role' => $role,
            'is_default' => true,
        ]);

        return $user;
    }

    protected function createProduct(Company $company, string $name, string $sku): Product
    {
        app(CompanyContext::class)->set($company);

        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 1000,
            'sale_price' => 1000,
            'cost_price' => 700,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }

    protected function createCustomer(Company $company, string $name, string $phone): Customer
    {
        app(CompanyContext::class)->set($company);

        return Customer::query()->create([
            'name' => $name,
            'phone' => $phone,
            'opening_balance' => 0,
            'is_active' => true,
        ]);
    }

    protected function createOrder(Company $company, string $orderNumber): Order
    {
        app(CompanyContext::class)->set($company);

        $customer = $this->createCustomer($company, $orderNumber.' Customer', '018'.random_int(10000000, 99999999));

        return Order::withoutEvents(fn (): Order => Order::query()->create([
            'company_id' => $company->getKey(),
            'order_number' => $orderNumber,
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'order_date' => now()->toDateString(),
            'subtotal' => 1000,
            'discount' => 0,
            'vat' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'status' => 'completed',
        ]));
    }
}
