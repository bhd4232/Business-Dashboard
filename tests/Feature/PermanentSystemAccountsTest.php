<?php

namespace Tests\Feature;

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PermanentSystemAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'System Account Company',
            'slug' => 'system-account-company',
            'invoice_prefix' => 'SAC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    public function test_every_company_receives_three_permanent_system_accounts(): void
    {
        $company = $this->company();

        $this->assertSame(
            ['customer_due', 'inventory_value', 'new_shipment'],
            Account::query()->whereNotNull('system_key')->orderBy('system_key')->pluck('system_key')->all(),
        );
        $this->assertDatabaseHas('accounts', [
            'company_id' => $company->getKey(),
            'name' => 'Inventory Value',
            'type' => 'inventory',
        ]);
        $this->assertDatabaseHas('accounts', [
            'company_id' => $company->getKey(),
            'name' => 'Customer Due',
            'type' => 'customer_due',
        ]);
        $this->assertDatabaseHas('accounts', [
            'company_id' => $company->getKey(),
            'name' => 'New Shipment',
            'type' => 'shipment',
        ]);
    }

    public function test_account_form_hides_opening_balance_and_system_types(): void
    {
        $company = $this->company();
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->session(['current_company_id' => $company->getKey()]);

        Livewire::actingAs($user)
            ->test(CreateAccount::class)
            ->assertSchemaComponentDoesNotExist('opening_balance')
            ->assertSchemaComponentExists('current_balance', 'form', fn (TextInput $field): bool => $field->getLabel() === 'Balance')
            ->assertSchemaComponentExists('type', 'form', fn (Select $field): bool => $field->getOptions() === Account::MANUAL_TYPES);
    }

    public function test_system_accounts_cannot_be_edited_or_deleted(): void
    {
        $this->company();
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $account = Account::query()->where('system_key', 'inventory_value')->sole();

        $this->actingAs($user);

        $this->assertFalse(AccountResource::canEdit($account));
        $this->assertFalse(AccountResource::canDelete($account));

        try {
            $account->update(['name' => 'Changed']);
            $this->fail('A permanent account was edited.');
        } catch (ValidationException) {
            $this->assertSame('Inventory Value', $account->fresh()->name);
        }

        $this->expectException(ValidationException::class);
        $account->delete();
    }

    public function test_system_account_balances_are_calculated_from_live_company_data(): void
    {
        $company = $this->company();

        Product::query()->create([
            'name' => 'Base Product',
            'sku' => 'BASE-001',
            'cost_price' => 25,
            'price' => 40,
            'stock' => 4,
            'has_variants' => false,
        ]);
        $variantProduct = Product::query()->create([
            'name' => 'Variant Product',
            'sku' => 'VAR-001',
            'cost_price' => 30,
            'price' => 50,
            'stock' => 0,
            'has_variants' => true,
        ]);
        ProductVariant::query()->create([
            'product_id' => $variantProduct->getKey(),
            'sku' => 'VAR-RED',
            'options' => ['Color' => 'Red'],
            'stock' => 2,
            'cost_price' => 40,
            'is_active' => true,
        ]);
        ProductVariant::query()->create([
            'product_id' => $variantProduct->getKey(),
            'sku' => 'VAR-BLUE',
            'options' => ['Color' => 'Blue'],
            'stock' => 3,
            'cost_price' => null,
            'is_active' => true,
        ]);

        Customer::query()->create(['name' => 'Due Customer One', 'opening_balance' => 300]);
        Customer::query()->create(['name' => 'Due Customer Two', 'opening_balance' => 200]);

        $supplier = Supplier::query()->create(['name' => 'Shipment Supplier']);
        $activePurchase = Purchase::withoutEvents(fn (): Purchase => Purchase::query()->create([
            'company_id' => $company->getKey(),
            'purchase_number' => 'PUR-SHIP-ACTIVE',
            'supplier_id' => $supplier->getKey(),
            'purchase_date' => now()->toDateString(),
            'total_amount' => 1200,
            'status' => 'draft',
        ]));
        $receivedPurchase = Purchase::withoutEvents(fn (): Purchase => Purchase::query()->create([
            'company_id' => $company->getKey(),
            'purchase_number' => 'PUR-SHIP-RECEIVED',
            'supplier_id' => $supplier->getKey(),
            'purchase_date' => now()->toDateString(),
            'total_amount' => 800,
            'status' => 'received',
        ]));

        Shipment::query()->create([
            'company_id' => $company->getKey(),
            'purchase_id' => $activePurchase->getKey(),
            'tracking_number' => 'SHIP-ACTIVE-1',
            'status' => 'in_transit',
        ]);
        Shipment::query()->create([
            'company_id' => $company->getKey(),
            'purchase_id' => $activePurchase->getKey(),
            'tracking_number' => 'SHIP-ACTIVE-2',
            'status' => 'booked',
        ]);
        Shipment::query()->create([
            'company_id' => $company->getKey(),
            'purchase_id' => $receivedPurchase->getKey(),
            'tracking_number' => 'SHIP-RECEIVED',
            'status' => 'received',
        ]);

        $this->assertSame(
            270.0,
            Account::query()->where('system_key', 'inventory_value')->sole()->balance(),
        );
        $this->assertSame(
            500.0,
            Account::query()->where('system_key', 'customer_due')->sole()->balance(),
        );
        $this->assertSame(
            1200.0,
            Account::query()->where('system_key', 'new_shipment')->sole()->balance(),
        );
    }
}
