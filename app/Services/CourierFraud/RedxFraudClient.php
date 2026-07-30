<?php

namespace App\Services\CourierFraud;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Direct HTTP replacement for shahariar-ahmad/courier-fraud-checker-bd's
 * RedxService::getCustomerDeliveryStats(). The access token is cached per
 * merchant account (keyed by username) rather than under one global key,
 * since credentials are now per-company instead of static config.
 */
class RedxFraudClient implements CourierFraudClient
{
    protected const TOKEN_CACHE_MINUTES = 50;

    public function checkByPhone(string $phone, array $credentials): ?array
    {
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (blank($username) || blank($password)) {
            return null;
        }

        try {
            $accessToken = $this->getAccessToken($username, $password);

            if (! $accessToken) {
                return null;
            }

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'Accept' => 'application/json, text/plain, */*',
                ])
                ->get("https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=88{$phone}");

            if ($response->status() === 401) {
                Cache::forget($this->tokenCacheKey($username));

                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $delivered = $response->json('data.deliveredParcels');
            $total = $response->json('data.totalParcels');

            if (! is_numeric($delivered) || ! is_numeric($total)) {
                return null;
            }

            $delivered = (int) $delivered;
            $total = (int) $total;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return [
            'success' => $delivered,
            'cancel' => max(0, $total - $delivered),
            'total' => $total,
        ];
    }

    protected function getAccessToken(string $username, string $password): ?string
    {
        $cacheKey = $this->tokenCacheKey($username);

        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept' => 'application/json, text/plain, */*',
        ])->post('https://api.redx.com.bd/v4/auth/login', [
            'phone' => '88'.$username,
            'password' => $password,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('data.accessToken');

        if ($token) {
            Cache::put($cacheKey, $token, now()->addMinutes(self::TOKEN_CACHE_MINUTES));
        }

        return $token;
    }

    protected function tokenCacheKey(string $username): string
    {
        return 'courier-fraud:redx:token:'.md5($username);
    }
}
