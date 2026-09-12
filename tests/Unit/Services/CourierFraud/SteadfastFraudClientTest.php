<?php

namespace Tests\Unit\Services\CourierFraud;

use App\Services\CourierFraud\SteadfastFraudClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteadfastFraudClientTest extends TestCase
{
    public function test_it_returns_null_when_credentials_are_missing(): void
    {
        $result = (new SteadfastFraudClient)->checkByPhone('01712345678', []);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_csrf_token_is_missing_from_login_page(): void
    {
        Http::fake([
            'steadfast.com.bd/login' => Http::response('<html>no token here</html>'),
        ]);

        $result = (new SteadfastFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_login_post_fails(): void
    {
        Http::fake($this->portal(loginPostStatus: 500));

        $result = (new SteadfastFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_the_login_silently_fails(): void
    {
        // A wrong/expired password still redirects (back to the login page)
        // rather than erroring, so the login step "succeeds" but the session
        // is never actually authenticated. The stats request then gets
        // re-served the login page's HTML instead of JSON — this must not
        // be read as a clean 0/0 delivery history.
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/user/frauds/check/')) {
                return Http::response('<html>login required</html>');
            }

            if ($method === 'GET' && str_contains($url, '/login')) {
                return Http::response('<input type="hidden" name="_token" value="tok">');
            }

            if ($method === 'POST' && str_contains($url, '/login')) {
                return Http::response('', 302);
            }

            return Http::response('', 404);
        });

        $result = (new SteadfastFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertNull($result);
    }

    public function test_it_returns_delivery_stats_on_success_and_logs_out(): void
    {
        Http::fake($this->portal(delivered: 6, cancelled: 4));

        $result = (new SteadfastFraudClient)->checkByPhone('01712345678', [
            'username' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->assertSame(['success' => 6, 'cancel' => 4, 'total' => 10], $result);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/logout'));
    }

    protected function portal(int $delivered = 0, int $cancelled = 0, int $loginPostStatus = 302): \Closure
    {
        return function ($request) use ($delivered, $cancelled, $loginPostStatus) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/user/frauds/check/')) {
                return Http::response(['total_delivered' => $delivered, 'total_cancelled' => $cancelled]);
            }

            if (str_contains($url, '/user/frauds/check')) {
                return Http::response('<meta name="csrf-token" content="tok">');
            }

            if (str_contains($url, '/logout')) {
                return Http::response('');
            }

            if ($method === 'GET' && str_contains($url, '/login')) {
                return Http::response('<input type="hidden" name="_token" value="tok">');
            }

            if ($method === 'POST' && str_contains($url, '/login')) {
                return Http::response('', $loginPostStatus);
            }

            return Http::response('', 404);
        };
    }
}
