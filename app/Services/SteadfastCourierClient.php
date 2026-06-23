<?php

namespace App\Services;

use App\Models\CourierProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SteadfastCourierClient
{
    public const DEFAULT_BASE_URL = 'https://portal.packzy.com/api/v1';

    public function createOrder(CourierProvider $provider, array $payload): array
    {
        return $this->request($provider)
            ->post($this->baseUrl($provider).'/create_order', $payload)
            ->throw()
            ->json();
    }

    public function statusByTrackingCode(CourierProvider $provider, string $trackingCode): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/status_by_trackingcode/'.$trackingCode)
            ->throw()
            ->json();
    }

    public function statusByInvoice(CourierProvider $provider, string $invoice): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/status_by_invoice/'.$invoice)
            ->throw()
            ->json();
    }

    public function balance(CourierProvider $provider): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/get_balance')
            ->throw()
            ->json();
    }

    protected function request(CourierProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        $secretKey = $credentials['secret_key'] ?? null;

        if (blank($apiKey) || blank($secretKey)) {
            throw ValidationException::withMessages([
                'credentials' => 'Steadfast API key and secret key are required.',
            ]);
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(3, 500, throw: false)
            ->withHeaders([
                'Api-Key' => $apiKey,
                'Secret-Key' => $secretKey,
                'Content-Type' => 'application/json',
            ]);
    }

    protected function baseUrl(CourierProvider $provider): string
    {
        return rtrim((string) (($provider->settings ?? [])['base_url'] ?? self::DEFAULT_BASE_URL), '/');
    }
}
