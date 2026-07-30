<?php

namespace Tests\Unit\Services\CourierFraud;

use App\Services\CourierFraud\PathaoFraudClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PathaoFraudClientTest extends TestCase
{
    public function test_it_returns_null_when_credentials_are_missing(): void
    {
        $result = (new PathaoFraudClient)->checkByPhone('01712345678', []);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_login_fails(): void
    {
        Http::fake([
            'merchant.pathao.com/api/v1/login' => Http::response([], 401),
        ]);

        $result = (new PathaoFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    public function test_it_returns_null_when_no_access_token_is_returned(): void
    {
        Http::fake([
            'merchant.pathao.com/api/v1/login' => Http::response(['access_token' => '']),
        ]);

        $result = (new PathaoFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
    }

    public function test_it_returns_delivery_stats_on_success(): void
    {
        Http::fake([
            'merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'tok-123']),
            'merchant.pathao.com/api/v1/user/success' => Http::response([
                'data' => [
                    'customer' => [
                        'successful_delivery' => 8,
                        'total_delivery' => 10,
                    ],
                ],
            ]),
        ]);

        $result = (new PathaoFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertSame(['success' => 8, 'cancel' => 2, 'total' => 10], $result);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/user/success')
            && $request->hasHeader('Authorization', 'Bearer tok-123'));
    }

    public function test_it_returns_null_when_success_lookup_fails(): void
    {
        Http::fake([
            'merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'tok-123']),
            'merchant.pathao.com/api/v1/user/success' => Http::response([], 500),
        ]);

        $result = (new PathaoFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
    }
}
