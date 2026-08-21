<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CourierProvider;
use App\Models\ExpenseCategory;
use App\Models\Investor;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Support\CompanyScopedUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * Regression tests for a class of bugs found during a full-schema audit
 * triggered by a live WooCommerce sync crash: several company-scoped tables
 * still carried a database-wide unique() constraint left over from before
 * multi-company support existed (categories.slug, fixed separately - see
 * WooCommerceImportTest), and several Filament admin forms independently
 * enforced "unique" without scoping the check to the current company at all
 * (blocking a legitimate cross-company duplicate with a false "already
 * taken" instead of a crash). Both halves are covered here.
 */
class CompanyScopedUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_companies_can_each_have_a_product_with_the_same_sku_and_barcode(): void
    {
        $companyA = $this->createCompany('sku-a', 'SKA');
        $companyB = $this->createCompany('sku-b', 'SKB');

        $this->makeProduct($companyA, 'DUP-SKU-01', 'BARCODE-01');
        $product = $this->makeProduct($companyB, 'DUP-SKU-01', 'BARCODE-01');

        $this->assertTrue($product->exists);
        $this->assertSame(2, Product::withoutGlobalScopes()->where('sku', 'DUP-SKU-01')->count());
    }

    public function test_two_companies_can_each_have_an_expense_category_with_the_same_slug(): void
    {
        $companyA = $this->createCompany('exp-a', 'EXA');
        $companyB = $this->createCompany('exp-b', 'EXB');

        app(CompanyContext::class)->set($companyA);
        ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent', 'is_active' => true]);

        app(CompanyContext::class)->set($companyB);
        $categoryB = ExpenseCategory::query()->create(['name' => 'Rent', 'slug' => 'rent', 'is_active' => true]);

        $this->assertTrue($categoryB->exists);
        $this->assertSame(2, ExpenseCategory::withoutGlobalScopes()->where('slug', 'rent')->count());
    }

    public function test_company_scoped_unique_rule_allows_cross_company_duplicates_but_blocks_same_company_duplicates(): void
    {
        $companyA = $this->createCompany('cp-a', 'CPA');
        $companyB = $this->createCompany('cp-b', 'CPB');

        app(CompanyContext::class)->set($companyA);
        CourierProvider::query()->create([
            'name' => 'Steadfast',
            'slug' => 'steadfast',
            'driver' => CourierProvider::DRIVER_STEADFAST,
            'is_active' => true,
        ]);

        // Cross-company: company B adding the same "steadfast" slug must pass
        // the exact validation rule ProductForm/CourierProviderResource/etc.
        // now use, matching what the database itself allows.
        app(CompanyContext::class)->set($companyB);
        $this->assertValidationPasses('courier_providers', 'slug', 'steadfast');

        // Same-company: a second distinct "steadfast" row within company A
        // itself must still be rejected as a duplicate.
        app(CompanyContext::class)->set($companyA);
        $this->assertValidationFails('courier_providers', 'slug', 'steadfast');
    }

    public function test_investor_phone_is_scoped_per_company_not_globally(): void
    {
        $companyA = $this->createCompany('inv-a', 'INA');
        $companyB = $this->createCompany('inv-b', 'INB');

        app(CompanyContext::class)->set($companyA);
        Investor::query()->create(['name' => 'Rahim', 'phone' => '01711111111']);

        // Before creating anything in company B, the exact rule the admin
        // form runs must already treat this phone as free for company B -
        // not blocked as "already taken" by company A's investor.
        app(CompanyContext::class)->set($companyB);
        $this->assertValidationPasses('investors', 'phone', '01711111111');

        // The same real person investing in a second company under the same
        // phone number must be allowed at the database level too.
        $investorB = Investor::query()->create(['name' => 'Rahim', 'phone' => '01711111111']);
        $this->assertTrue($investorB->exists);
    }

    private function assertValidationPasses(string $table, string $column, string $value): void
    {
        $rule = Rule::unique($table, $column);
        $rule = (CompanyScopedUnique::rule())($rule, null);

        $validator = Validator::make([$column => $value], [$column => $rule]);

        $this->assertFalse($validator->fails(), "Expected {$table}.{$column} = '{$value}' to validate as unique within the current company, but it failed: ".json_encode($validator->errors()->all()));
    }

    private function assertValidationFails(string $table, string $column, string $value): void
    {
        $rule = Rule::unique($table, $column);
        $rule = (CompanyScopedUnique::rule())($rule, null);

        $validator = Validator::make([$column => $value], [$column => $rule]);

        $this->assertTrue($validator->fails(), "Expected {$table}.{$column} = '{$value}' to fail uniqueness within the same company, but it passed.");
    }

    private function createCompany(string $slug, string $invoicePrefix): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'invoice_prefix' => $invoicePrefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    private function makeProduct(Company $company, string $sku, string $barcode): Product
    {
        app(CompanyContext::class)->set($company);

        return Product::query()->create([
            'name' => 'Product '.$sku,
            'sku' => $sku,
            'barcode' => $barcode,
            'slug' => strtolower($sku),
            'unit' => 'pcs',
            'price' => 100,
            'sale_price' => 100,
            'cost_price' => 0,
            'stock' => 0,
            'reorder_level' => 0,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }
}
