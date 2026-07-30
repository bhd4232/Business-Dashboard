<?php

namespace Tests\Unit\Services\CourierFraud;

use App\Services\CourierFraud\RedxFraudClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RedxFraudClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_returns_null_when_credentials_are_missing(): void
    {
        $result = (new RedxFraudClient)->checkByPhone('01712345678', []);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_login_fails(): void
    {
        Http::fake([
            'api.redx.com.bd/v4/auth/login' => Http::response([], 401),
        ]);

        $result = (new RedxFraudClient)->checkByPhone('01712345678', [
            'username' => '01900000000',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
    }

    public function test_it_returns_delivery_stats_on_success_and_caches_the_token(): void
    {
        Http::fake([
            'api.redx.com.bd/v4/auth/login' => Http::response(['data' => ['accessToken' => 'tok-123']]),
            'redx.com.bd/api/redx_se/*' => Http::response([
                'data' => ['deliveredParcels' => 7, 'totalParcels' => 9],
            ]),
        ]);

        $result = (new RedxFraudClient)->checkByPhone('01712345678', [
            'username' => '01900000000',
            'password' => 'secret',
        ]);

        $this->assertSame(['success' => 7, 'cancel' => 2, 'total' => 9], $result);

        // Second call should reuse the cached token instead of logging in again.
        (new RedxFraudClient)->checkByPhone('01712345678', [
            'username' => '01900000000',
            'password' => 'secret',
        ]);

        Http::assertSentCount(3);
    }

    public function test_it_clears_cached_token_and_returns_null_on_401(): void
    {
        Cache::put('courier-fraud:redx:token:'.md5('01900000000'), 'stale-token', now()->addMinutes(10));

        Http::fake([
            'redx.com.bd/api/redx_se/*' => Http::response([], 401),
        ]);

        $result = (new RedxFraudClient)->checkByPhone('01712345678', [
            'username' => '01900000000',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
        $this->assertNull(Cache::get('courier-fraud:redx:token:'.md5('01900000000')));
    }

    public function test_it_returns_null_when_response_fields_are_not_numeric(): void
    {
        Http::fake([
            'api.redx.com.bd/v4/auth/login' => Http::response(['data' => ['accessToken' => 'tok-123']]),
            'redx.com.bd/api/redx_se/*' => Http::response([
                'data' => [
                    'deliveredParcels' => 'Threshold hit, wait a minute',
                    'totalParcels' => 'Threshold hit, wait a minute',
                ],
            ]),
        ]);

        $result = (new RedxFraudClient)->checkByPhone('01712345678', [
            'username' => '01900000000',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
    }
}
