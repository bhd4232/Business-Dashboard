<?php

namespace App\Services\CourierFraud;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Direct HTTP replacement for shahariar-ahmad/courier-fraud-checker-bd's
 * PathaoService::pathao(). Same two-step flow (merchant login, then the
 * customer success-ratio lookup) but credentials are passed in per-call
 * instead of read from static config, and failures return null instead of
 * an ['error' => ...] array.
 */
class PathaoFraudClient implements CourierFraudClient
{
    public function checkByPhone(string $phone, array $credentials): ?array
    {
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (blank($username) || blank($password)) {
            return null;
        }

        try {
            $login = Http::post('https://merchant.pathao.com/api/v1/login', [
                'username' => $username,
                'password' => $password,
            ]);

            if (! $login->successful()) {
                return null;
            }

            $accessToken = trim((string) $login->json('access_token'));

            if ($accessToken === '') {
                return null;
            }

            $response = Http::withToken($accessToken)
                ->post('https://merchant.pathao.com/api/v1/user/success', [
                    'phone' => $phone,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $delivered = (int) ($response->json('data.customer.successful_delivery') ?? 0);
            $total = (int) ($response->json('data.customer.total_delivery') ?? 0);
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
}
