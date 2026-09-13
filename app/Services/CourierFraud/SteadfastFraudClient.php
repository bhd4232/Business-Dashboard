<?php

namespace App\Services\CourierFraud;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Direct HTTP replacement for shahariar-ahmad/courier-fraud-checker-bd's
 * SteadfastService::steadfast(). Steadfast has no public fraud-check API,
 * so this replicates the package's approach of driving the merchant web
 * portal directly: fetch the login page for a CSRF token, log in with
 * cookies, read the fraud-check page for the phone number, then log out.
 */
class SteadfastFraudClient implements CourierFraudClient
{
    protected const BASE_URL = 'https://steadfast.com.bd';

    public function checkByPhone(string $phone, array $credentials): ?array
    {
        $email = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (blank($email) || blank($password)) {
            return null;
        }

        try {
            $loginPage = Http::get(self::BASE_URL.'/login');

            if (! preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $loginPage->body(), $matches)) {
                return null;
            }

            $token = $matches[1];

            $loginResponse = Http::withCookies($this->cookieArray($loginPage), 'steadfast.com.bd')
                ->asForm()
                ->post(self::BASE_URL.'/login', [
                    '_token' => $token,
                    'email' => $email,
                    'password' => $password,
                ]);

            if (! $loginResponse->successful() && ! $loginResponse->redirect()) {
                return null;
            }

            $sessionCookies = $this->cookieArray($loginResponse);

            $statsResponse = Http::withCookies($sessionCookies, 'steadfast.com.bd')
                ->get(self::BASE_URL."/user/frauds/check/{$phone}");

            if (! $statsResponse->successful()) {
                return null;
            }

            $stats = $statsResponse->json();

            // The login POST redirects on both a correct and a wrong
            // password, so a wrong/expired password still lands here as a
            // "successful" request — just re-served Steadfast's login page
            // (HTML, not JSON) because the session was never authenticated.
            // Treat a response without the real keys as a failed check
            // rather than defaulting to 0, which would misreport a clean
            // delivery history for a phone number Steadfast never actually
            // looked up.
            if (! is_array($stats) || ! array_key_exists('total_delivered', $stats) || ! array_key_exists('total_cancelled', $stats)) {
                return null;
            }

            $delivered = (int) $stats['total_delivered'];
            $cancelled = (int) $stats['total_cancelled'];

            $this->logout($sessionCookies);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return [
            'success' => $delivered,
            'cancel' => $cancelled,
            'total' => $delivered + $cancelled,
        ];
    }

    protected function cookieArray(Response $response): array
    {
        $cookies = [];

        foreach ($response->cookies()->toArray() as $cookie) {
            $cookies[$cookie['Name']] = $cookie['Value'];
        }

        return $cookies;
    }

    protected function logout(array $cookies): void
    {
        $page = Http::withCookies($cookies, 'steadfast.com.bd')->get(self::BASE_URL.'/user/frauds/check');

        if (! $page->successful() || ! preg_match('/<meta name="csrf-token" content="(.*?)"/', $page->body(), $matches)) {
            return;
        }

        Http::withCookies($cookies, 'steadfast.com.bd')
            ->asForm()
            ->post(self::BASE_URL.'/logout', ['_token' => $matches[1]]);
    }
}
