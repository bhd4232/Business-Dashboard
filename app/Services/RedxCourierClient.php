<?php

namespace App\Services;

use App\Models\CourierProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RedxCourierClient
{
    public const DEFAULT_BASE_URL = 'https://openapi.redx.com.bd';

    public const SANDBOX_BASE_URL = 'https://sandbox.redx.com.bd';

    public function createParcel(CourierProvider $provider, array $payload): array
    {
        return $this->request($provider)
            ->post($this->baseUrl($provider).'/v1.0.0-beta/parcel', $payload)
            ->throw()
            ->json();
    }

    public function parcelInfo(CourierProvider $provider, string $trackingId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/v1.0.0-beta/parcel/info/'.$trackingId)
            ->throw()
            ->json();
    }

    public function track(CourierProvider $provider, string $trackingId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/v1.0.0-beta/parcel/track/'.$trackingId)
            ->throw()
            ->json();
    }

    public function areas(CourierProvider $provider): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/v1.0.0-beta/areas')
            ->throw()
            ->json();
    }

    protected function request(CourierProvider $provider): PendingRequest
    {
        $accessToken = ($provider->credentials ?? [])['access_token'] ?? null;

        if (blank($accessToken)) {
            throw ValidationException::withMessages([
                'credentials' => 'RedX API access token is required.',
            ]);
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(3, 500, throw: false)
            ->withHeaders([
                'API-ACCESS-TOKEN' => 'Bearer '.$accessToken,
            ]);
    }

    protected function baseUrl(CourierProvider $provider): string
    {
        return rtrim((string) (($provider->settings ?? [])['base_url'] ?? self::DEFAULT_BASE_URL), '/');
    }
}
