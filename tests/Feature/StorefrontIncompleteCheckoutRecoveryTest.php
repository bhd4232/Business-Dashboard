<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontCartRecord;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontIncompleteCheckoutRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_checkout_autosave_captures_valid_partial_contact_and_cart_value(): void
    {
        $company = $this->store('autosave.example.test', ['checkout_autosave_enabled' => true]);
        $this->addProduct($company, 'AUTOSAVE-1');

        $this->get('http://autosave.example.test/checkout')->assertOk();
        $record = StorefrontCartRecord::withoutGlobalScopes()->sole();
        $this->assertSame(StorefrontCartRecord::STATUS_CHECKOUT_STARTED, $record->status);
        $this->assertNotNull($record->checkout_started_at);
        $this->assertSame(900.0, (float) $record->subtotal);

        $this->postJson('http://autosave.example.test/checkout/autosave', [
            'name' => 'Autosave Buyer',
            'phone' => '+8801712345678',
            'email' => 'Buyer@Example.Test',
            'address' => 'Mirpur, Dhaka',
        ])->assertNoContent();

        $record->refresh();
        $this->assertSame('Autosave Buyer', $record->customer_name);
        $this->assertSame('01712345678', $record->phone);
        $this->assertSame('buyer@example.test', $record->email);
        $this->assertSame('Mirpur, Dhaka', $record->address);
        $this->assertNotNull($record->last_activity_at);
    }

    public function test_disabled_autosave_does_not_capture_checkout_pii(): void
    {
        $company = $this->store('autosave-off.example.test', ['checkout_autosave_enabled' => false]);
        $this->addProduct($company, 'AUTOSAVE-OFF-1');

        $this->postJson('http://autosave-off.example.test/checkout/autosave', [
            'name' => 'Should Not Persist',
            'phone' => '01712345678',
            'email' => 'private@example.test',
            'address' => 'Private address',
        ])->assertNoContent();

        $record = StorefrontCartRecord::withoutGlobalScopes()->sole();
        $this->assertNull($record->customer_name);
        $this->assertNull($record->phone);
        $this->assertNull($record->email);
        $this->assertNull($record->address);
    }

    public function test_reminder_link_restores_company_cart_and_conversion_links_recovered_order(): void
    {
        $company = $this->store('recover.example.test', [
            'checkout_autosave_enabled' => true,
            'abandoned_cart_reminders_enabled' => true,
            'abandoned_cart_delay_hours' => 1,
            'notification_credentials' => [
                'sms_api_url' => 'http://sms.example.test/send?to={phone}&msg={message}',
            ],
        ]);
        $otherCompany = $this->store('other-recover.example.test');
        $this->addProduct($company, 'RECOVER-1');
        $this->get('http://recover.example.test/checkout')->assertOk();
        $this->postJson('http://recover.example.test/checkout/autosave', [
            'name' => 'Recovery Buyer',
            'phone' => '01710000123',
            'email' => 'recover@example.test',
            'address' => 'Dhaka',
        ])->assertNoContent();

        $record = StorefrontCartRecord::withoutGlobalScopes()->where('company_id', $company->getKey())->sole();
        StorefrontCartRecord::withoutGlobalScopes()->whereKey($record->getKey())->update(['updated_at' => now()->subHours(2)]);
        Http::fake(['sms.example.test/*' => Http::response('ok')]);

        $this->artisan('storefront:send-abandoned-cart-reminders')
            ->expectsOutputToContain('reminders sent: 1')
            ->assertSuccessful();

        $record->refresh();
        $this->assertSame(StorefrontCartRecord::STATUS_REMINDED, $record->status);
        $this->assertSame(1, $record->reminder_count);
        $this->assertSame(['sms'], $record->last_reminder_channels);
        $recoveryUrl = $record->recoveryUrl();
        $this->assertNotNull($recoveryUrl);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), $recoveryUrl));

        $path = parse_url($recoveryUrl, PHP_URL_PATH);
        $this->get('http://other-recover.example.test'.$path)->assertNotFound();
        $this->get('http://recover.example.test'.preg_replace('/[a-f0-9]{64}$/', str_repeat('0', 64), $path))->assertNotFound();
        $this->get('http://recover.example.test'.$path)
            ->assertRedirect('http://recover.example.test/cart')
            ->assertSessionHas('storefront_status');
        $this->assertNotNull($record->fresh()->recovery_opened_at);

        $this->post('http://recover.example.test/checkout', [
            'name' => 'Recovery Buyer',
            'phone' => '01710000123',
            'email' => 'recover@example.test',
            'address' => 'Dhaka',
            'payment_method' => 'cod',
        ])->assertRedirectContains('/checkout/success/');

        $order = Order::withoutGlobalScopes()->where('company_id', $company->getKey())->sole();
        $record->refresh();
        $this->assertSame(StorefrontCartRecord::STATUS_CONVERTED, $record->status);
        $this->assertSame($order->getKey(), $record->recovered_order_id);
        $this->assertNotNull($record->converted_at);
        $this->assertNotNull($record->recovered_at);

        // A later cart uses a fresh token/record, preserving recovery history.
        $this->addProduct($company, 'RECOVER-2');
        $this->assertSame(2, StorefrontCartRecord::withoutGlobalScopes()->where('company_id', $company->getKey())->count());
        $this->assertSame(0, StorefrontCartRecord::withoutGlobalScopes()->where('company_id', $otherCompany->getKey())->count());
    }

    public function test_incomplete_checkout_filament_list_and_detail_use_default_components(): void
    {
        $company = $this->store('recovery-admin.example.test', ['checkout_autosave_enabled' => true]);
        $this->addProduct($company, 'RECOVERY-ADMIN-1');
        $this->get('http://recovery-admin.example.test/checkout')->assertOk();
        $record = StorefrontCartRecord::withoutGlobalScopes()->sole();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/storefront/storefront-cart-records')
            ->assertOk()
            ->assertSee('Checkout Recovery')
            ->assertSee('Still incomplete');

        $this->actingAs($admin)
            ->get('/admin/storefront/storefront-cart-records/'.$record->getKey())
            ->assertOk()
            ->assertSee('Recovery lifecycle')
            ->assertSee('Cart snapshot');
    }

    private function store(string $domain, array $overrides = []): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'RCV-'.strtoupper(substr(md5($domain), 0, 6)),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);
        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'cod_enabled' => true,
            'new_customer_delivery_advance_enabled' => false,
            'delivery_first_kg_inside' => 70,
            'delivery_first_kg_outside' => 110,
            'delivery_additional_per_kg' => 20,
        ], $overrides));

        return $company;
    }

    private function addProduct(Company $company, string $sku): void
    {
        app(CompanyContext::class)->set($company);
        $product = Product::query()->create([
            'name' => 'Recovery Product '.$sku,
            'sku' => $sku,
            'price' => 900,
            'sale_price' => 900,
            'cost_price' => 500,
            'stock' => 10,
            'unit' => 'pcs',
            'weight_kg' => 1,
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        app(StorefrontCart::class)->add($company, $product, 1);
    }
}
