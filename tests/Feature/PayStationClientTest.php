<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StorefrontSetting;
use App\Services\PayStationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PayStationClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_configured_requires_enabled_and_both_credentials(): void
    {
        $this->assertFalse(PayStationClient::isConfigured($this->setting([
            'online_payment_enabled' => false,
            'payment_credentials' => ['paystation_merchant_id' => 'M1', 'paystation_password' => 'secret'],
        ])));

        $this->assertFalse(PayStationClient::isConfigured($this->setting([
            'online_payment_enabled' => true,
            'payment_credentials' => ['paystation_merchant_id' => 'M1'],
        ])));

        $this->assertTrue(PayStationClient::isConfigured($this->setting([
            'online_payment_enabled' => true,
            'payment_credentials' => ['paystation_merchant_id' => 'M1', 'paystation_password' => 'secret'],
        ])));
    }

    public function test_create_payment_grants_a_token_then_sends_the_documented_fields(): void
    {
        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['status_code' => '200', 'status' => 'success', 'token' => 'tok-abc']),
            'api.paystation.com.bd/create-payment' => Http::response(['status_code' => 200, 'status' => 'success', 'payment_url' => 'https://pay.paystation.com.bd/checkout/x1']),
        ]);

        $result = (new PayStationClient)->createPayment(
            $this->setting(),
            1000.5,
            'Jane Buyer',
            'jane@example.com',
            '01712345678',
            'Dhaka',
            'https://shop.test/checkout/payment/1/return?expires=1&signature=abc',
            'https://shop.test/checkout',
            'https://shop.test/webhooks/paystation/1',
            merchantReference: '1',
            metadata: ['purpose' => 'checkout_advance'],
        );

        $this->assertSame('https://pay.paystation.com.bd/checkout/x1', $result['payment_url']);
        $this->assertSame('1', $result['invoice_id']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/grant-token')
            && $request->hasHeader('merchantId', 'test-mid')
            && $request->hasHeader('password', 'test-pass'));

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/create-payment')) {
                return false;
            }

            return $request->hasHeader('token', 'tok-abc')
                && $request['invoice_number'] === '1'
                && $request['currency'] === 'BDT'
                && (float) $request['payment_amount'] === 1000.5
                && $request['cust_name'] === 'Jane Buyer'
                && $request['cust_phone'] === '01712345678'
                && $request['cust_email'] === 'jane@example.com'
                && $request['cust_address'] === 'Dhaka'
                && $request['callback_url'] === 'https://shop.test/checkout/payment/1/return?expires=1&signature=abc';
        });
    }

    public function test_create_payment_throws_when_paystation_reports_a_non_success_status(): void
    {
        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['token' => 'tok-abc']),
            'api.paystation.com.bd/create-payment' => Http::response(['status_code' => 400, 'status' => 'failed', 'message' => 'Invalid amount']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid amount');

        (new PayStationClient)->createPayment(
            $this->setting(), 100, 'Jane', null, '017', 'Dhaka', 'https://shop.test/return', 'https://shop.test', 'https://shop.test/webhook', '1',
        );
    }

    public function test_verify_payment_requires_a_transaction_id_and_makes_no_request_without_one(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        try {
            (new PayStationClient)->verifyPayment($this->setting(), '1', null);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_verify_payment_sends_invoice_number_and_trx_id_and_normalizes_the_response(): void
    {
        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['token' => 'tok-abc']),
            'api.paystation.com.bd/retrive-transaction' => Http::response([
                'status_code' => 200,
                'status' => 'success',
                'invoice_number' => '1',
                'trx_status' => 'Success',
                'trx_id' => 'TRX-1',
                'payment_amount' => '1000.50',
                'payment_method' => 'bkash',
            ]),
        ]);

        $result = (new PayStationClient)->verifyPayment($this->setting(), '1', 'TRX-1');

        $this->assertSame('COMPLETED', $result['status']);
        $this->assertSame('1000.50', $result['amount']);
        $this->assertSame('TRX-1', $result['transaction_id']);
        $this->assertSame('bkash', $result['payment_method']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/retrive-transaction')
            && $request['invoice_number'] === '1'
            && $request['trx_id'] === 'TRX-1');
    }

    public function test_verify_payment_maps_a_failed_transaction_status(): void
    {
        Http::fake([
            'api.paystation.com.bd/grant-token' => Http::response(['token' => 'tok-abc']),
            'api.paystation.com.bd/retrive-transaction' => Http::response([
                'status_code' => 200, 'status' => 'success', 'trx_status' => 'Failed', 'payment_amount' => '1000', 'trx_id' => 'TRX-1',
            ]),
        ]);

        $result = (new PayStationClient)->verifyPayment($this->setting(), '1', 'TRX-1');

        $this->assertSame('FAILED', $result['status']);
    }

    protected function setting(array $overrides = []): StorefrontSetting
    {
        $suffix = (string) Company::query()->count();

        $company = Company::query()->create([
            'name' => 'PayStation Client Co '.$suffix, 'slug' => 'paystation-client-co-'.$suffix,
            'invoice_prefix' => 'PSC'.$suffix, 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);

        return new StorefrontSetting(array_merge([
            'company_id' => $company->getKey(),
            'online_payment_enabled' => true,
            'online_payment_gateway' => 'paystation',
            'payment_credentials' => [
                'paystation_merchant_id' => 'test-mid',
                'paystation_password' => 'test-pass',
            ],
        ], $overrides));
    }
}
