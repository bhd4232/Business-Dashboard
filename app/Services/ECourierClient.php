<?php

namespace App\Services;

use App\Models\CourierProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ECourierClient
{
    public const DEFAULT_BASE_URL = 'https://backoffice.ecourier.com.bd/api';

    public const STAGING_BASE_URL = 'https://staging.ecourier.com.bd/api';

    public function placeOrder(CourierProvider $provider, array $payload): array
    {
        return $this->post($provider, 'order-place', $payload);
    }

    public function track(CourierProvider $provider, string $ecr): array
    {
        return $this->post($provider, 'track', ['ecr' => $ecr]);
    }

    public function cancelOrder(CourierProvider $provider, string $tracking): array
    {
        return $this->post($provider, 'cancel-order', ['tracking' => $tracking]);
    }

    public function cities(CourierProvider $provider): array
    {
        return $this->post($provider, 'city-list');
    }

    public function thanas(CourierProvider $provider, string $city): array
    {
        return $this->post($provider, 'thana-list', ['city' => $city]);
    }

    public function postcodes(CourierProvider $provider, string $city, string $thana): array
    {
        return $this->post($provider, 'postcode-list', ['city' => $city, 'thana' => $thana]);
    }

    public function packages(CourierProvider $provider): array
    {
        return $this->post($provider, 'packages');
    }

    protected function post(CourierProvider $provider, string $endpoint, array $payload = []): array
    {
        return $this->request($provider)
            ->post($this->baseUrl($provider).'/'.$endpoint, $payload)
            ->throw()
            ->json();
    }

    protected function request(CourierProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];

        foreach (['api_key', 'api_secret', 'user_id'] as $field) {
            if (blank($credentials[$field] ?? null)) {
                throw ValidationException::withMessages([
                    'credentials' => 'E-Courier API key, API secret, and user ID are required.',
                ]);
            }
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(3, 500, throw: false)
            ->withHeaders([
                'API-KEY' => $credentials['api_key'],
                'API-SECRET' => $credentials['api_secret'],
                'USER-ID' => $credentials['user_id'],
            ]);
    }

    protected function baseUrl(CourierProvider $provider): string
    {
        return rtrim((string) (($provider->settings ?? [])['base_url'] ?? self::DEFAULT_BASE_URL), '/');
    }
}
