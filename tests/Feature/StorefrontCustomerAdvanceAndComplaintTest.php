<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontCartRecord;
use App\Models\StorefrontCheckoutAttempt;
use App\Models\StorefrontComplaint;
use App\Models\StorefrontPayment;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\StorefrontCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StorefrontCustomerAdvanceAndComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Queue::fake();
    }

    public function test_new_customer_order_is_created_only_after_weight_based_delivery_payment_is_verified(): void
    {
        $company = $this->store('advance.example.test', [
            'online_payment_enabled' => true,
            'payment_credentials' => ['zinipay_api_key' => 'test_key'],
            'new_customer_delivery_advance_enabled' => true,
            'checkout_autosave_enabled' => true,
            'checkout_policy_mode' => StorefrontSetting::CHECKOUT_POLICY_ENFORCE,
        ]);
        app(CompanyContext::class)->set($company);
        $product = $this->product('Five Kilo Item', 'FIVE-KG', 5);
        app(StorefrontCart::class)->add($company, $product, 1);

        Http::fake([
            'api.zinipay.com/v1/payment/create' => Http::response([
                'payment_url' => 'https://pay.zinipay.com/invoice/DELIVERY-150',
            ]),
        ]);

        $this->post('http://advance.example.test/checkout', [
            'name' => 'New Buyer',
            'phone' => '01710000001',
            'address' => 'Dhaka',
            'delivery_area' => 'inside',
            'payment_method' => 'cod',
        ])->assertRedirect('https://pay.zinipay.com/invoice/DELIVERY-150');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, Customer::withoutGlobalScopes()->count());
        $payment = StorefrontPayment::withoutGlobalScopes()->sole();
        $this->assertSame(150.0, (float) $payment->amount);
        $this->assertSame(StorefrontPayment::PURPOSE_CHECKOUT_ADVANCE, $payment->purpose);
        $this->assertNull($payment->order_id);
        $attempt = StorefrontCheckoutAttempt::withoutGlobalScopes()->sole();
        $this->assertSame(StorefrontCheckoutAttempt::OUTCOME_PENDING_PAYMENT, $attempt->outcome);
        $this->assertSame($attempt->getKey(), (int) $payment->checkout_data['checkout_attempt_id']);
        $cartRecord = StorefrontCartRecord::withoutGlobalScopes()->sole();
        $this->assertSame($cartRecord->getKey(), (int) $payment->checkout_data['cart_record_id']);

        Http::fake([
            'api.zinipay.com/v1/payment/verify' => Http::response([
                'amount' => 150,
                'invoice_id' => 'DELIVERY-150',
                'payment_method' => 'bkash',
                'transaction_id' => 'DELIVERY-TRX-1',
                'status' => 'COMPLETED',
            ]),
        ]);

        $this->postJson('http://advance.example.test/webhooks/zinipay/'.$payment->getKey(), [
            'invoice_id' => 'DELIVERY-150',
        ])->assertOk();

        $order = Order::withoutGlobalScopes()->with('customer')->sole();
        $this->assertSame('01710000001', $order->customer->phone);
        $this->assertSame(150.0, (float) $order->shipping_fee);
        $this->assertSame(150.0, (float) $order->paid_amount);
        $this->assertSame($order->getKey(), $payment->fresh()->order_id);
        $this->assertSame(StorefrontCheckoutAttempt::OUTCOME_ACCEPTED, $attempt->fresh()->outcome);
        $this->assertSame($order->getKey(), $attempt->fresh()->order_id);
        $this->assertSame(StorefrontCartRecord::STATUS_CONVERTED, $cartRecord->fresh()->status);
        $this->assertSame($order->getKey(), $cartRecord->fresh()->recovered_order_id);
    }

    public function test_outside_dhaka_rate_uses_first_kg_plus_each_additional_started_kg(): void
    {
        $company = $this->store('quote.example.test');
        app(CompanyContext::class)->set($company);
        $product = $this->product('Weighted Item', 'WEIGHTED', 4.2);
        app(StorefrontCart::class)->add($company, $product, 1);

        $this->get('http://quote.example.test/checkout')
            ->assertOk()
            ->assertSee('Enter the full address to calculate delivery automatically.')
            ->assertDontSee('name="delivery_area"', false)
            ->assertSee('4.200 kg (charged as 5 kg)');
    }

    public function test_complaint_is_stored_company_wise_and_sent_to_that_company_telegram_chat(): void
    {
        $company = $this->store('complaint.example.test', [
            'complaint_telegram_enabled' => true,
            'telegram_credentials' => ['bot_token' => 'company_bot', 'chat_id' => '-100123'],
        ]);
        app(CompanyContext::class)->set($company);

        Http::fake([
            'api.telegram.org/botcompany_bot/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 88],
            ]),
        ]);

        $this->post('http://complaint.example.test/complaints', [
            'order_reference' => 'INV-UNKNOWN-1',
            'customer_name' => 'Complaint Buyer',
            'phone' => '01710000002',
            'issue_type' => 'damaged',
            'details' => 'The product arrived with visible damage.',
        ])->assertRedirect('http://complaint.example.test/complaints');

        $complaint = StorefrontComplaint::withoutGlobalScopes()->sole();
        $this->assertSame($company->getKey(), $complaint->company_id);
        $this->assertSame('sent', $complaint->telegram_status);
        $this->assertSame('88', $complaint->telegram_message_id);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/botcompany_bot/sendMessage'
            && $request['chat_id'] === '-100123'
            && str_contains($request['text'], $complaint->complaint_number));
    }

    public function test_filament_complaint_list_and_resolution_form_render_with_default_components(): void
    {
        $company = $this->store('complaint-admin.example.test');
        app(CompanyContext::class)->set($company);
        $complaint = StorefrontComplaint::query()->create([
            'company_id' => $company->getKey(),
            'order_reference' => 'ADV-20260806-0001',
            'customer_name' => 'Admin Review Buyer',
            'phone' => '01710000003',
            'issue_type' => 'quantity_mismatch',
            'details' => 'The delivered quantity was lower than the invoice quantity.',
        ]);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->id])
            ->get('/admin/storefront/storefront-complaints')
            ->assertOk()
            ->assertSee($complaint->complaint_number);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->id])
            ->get('/admin/storefront/storefront-complaints/'.$complaint->getKey().'/edit')
            ->assertOk()
            ->assertSee('Resolution');
    }

    private function store(string $domain, array $overrides = []): Company
    {
        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'ADV',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
            'cod_enabled' => true,
            'delivery_first_kg_inside' => 70,
            'delivery_first_kg_outside' => 110,
            'delivery_additional_per_kg' => 20,
        ], $overrides));

        return $company;
    }

    private function product(string $name, string $sku, float $weight): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'price' => 1000,
            'sale_price' => 1000,
            'cost_price' => 600,
            'stock' => 10,
            'unit' => 'pcs',
            'weight_kg' => $weight,
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
    }
}
