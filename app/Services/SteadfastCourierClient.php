<?php

namespace App\Services;

use App\Models\CourierProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Steadfast doesn't publish a canonical machine-readable spec for the
 * return/payment endpoints, so their paths were cross-checked against two
 * independent open-source Laravel wrapper packages (nayemuf/steadfast-courier
 * and sabitahmadumid/laravel-steadfast, both August 2026) which agree on the
 * same paths. Confirmed live against a real merchant account: on 2026-08-13
 * get_balance and payments() succeeded (the earlier /payment and /return/{id}
 * paths 404'd and were corrected to /payments and /get_return_request/{id}
 * below); on 2026-08-14, with API credentials connected locally, both
 * payments() and payment() were exercised end-to-end and returned real
 * settlement + consignment data — see the docblock on
 * `CourierPaymentHistory` for the confirmed response shape. returnStatus()
 * and returnRequests() are corrected by the same cross-check but not yet
 * exercised against a live return.
 */
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

    public function createReturnRequest(CourierProvider $provider, array $payload): array
    {
        return $this->request($provider)
            ->post($this->baseUrl($provider).'/create_return_request', $payload)
            ->throw()
            ->json();
    }

    public function returnStatus(CourierProvider $provider, int|string $returnId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/get_return_request/'.$returnId)
            ->throw()
            ->json();
    }

    public function returnRequests(CourierProvider $provider, array $query = []): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/get_return_requests', $query)
            ->throw()
            ->json();
    }

    public function payments(CourierProvider $provider, array $query = []): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/payments', $query)
            ->throw()
            ->json();
    }

    /**
     * A single payment/settlement's detail, including the consignments
     * cleared under it — mirrors the Steadfast merchant app's "Payment
     * Details" drill-down screen.
     */
    public function payment(CourierProvider $provider, int|string $paymentId): array
    {
        return $this->request($provider)
            ->get($this->baseUrl($provider).'/payments/'.$paymentId)
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
