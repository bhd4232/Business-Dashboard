<?php

namespace App\Services;

use App\Models\CourierProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PathaoCourierClient
{
    public const DEFAULT_BASE_URL = 'https://api-hermes.pathao.com';

    public const SANDBOX_BASE_URL = 'https://courier-api-sandbox.pathao.com';

    public function createOrder(CourierProvider $provider, array $payload): array
    {
        return $this->request($provider)
            ->post($this->baseUrl($provider).'/aladdin/api/v1/orders', $payload)
            ->throw()
            ->json();
    }

    public function orderInfo(CourierProvider $provider, string $consignmentId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/aladdin/api/v1/orders/'.$consignmentId.'/info')
            ->throw()
            ->json();
    }

    public function cities(CourierProvider $provider): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/aladdin/api/v1/countries/1/city-list')
            ->throw()
            ->json();
    }

    public function zones(CourierProvider $provider, int $cityId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/aladdin/api/v1/cities/'.$cityId.'/zone-list')
            ->throw()
            ->json();
    }

    public function areas(CourierProvider $provider, int $zoneId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/aladdin/api/v1/zones/'.$zoneId.'/area-list')
            ->throw()
            ->json();
    }

    public function stores(CourierProvider $provider): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/aladdin/api/v1/stores')
            ->throw()
            ->json();
    }

    protected function request(CourierProvider $provider): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(3, 500, throw: false)
            ->withToken($this->accessToken($provider));
    }

    protected function accessToken(CourierProvider $provider): string
    {
        $cacheKey = 'pathao-courier-token-'.$provider->getKey();
        $token = Cache::get($cacheKey);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $credentials = $provider->credentials ?? [];

        foreach (['client_id', 'client_secret', 'username', 'password'] as $field) {
            if (blank($credentials[$field] ?? null)) {
                throw ValidationException::withMessages([
                    'credentials' => 'Pathao client ID, client secret, username, and password are required.',
                ]);
            }
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->post($this->baseUrl($provider).'/aladdin/api/v1/issue-token', [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'grant_type' => 'password',
            ])
            ->throw()
            ->json();

        $token = (string) ($response['access_token'] ?? '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'pathao' => 'Pathao did not return an access token. Check the credentials.',
            ]);
        }

        Cache::put($cacheKey, $token, max(60, (int) ($response['expires_in'] ?? 3600) - 60));

        return $token;
    }

    protected function baseUrl(CourierProvider $provider): string
    {
        return rtrim((string) (($provider->settings ?? [])['base_url'] ?? self::DEFAULT_BASE_URL), '/');
    }
}
