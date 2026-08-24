<?php

namespace App\Services\Couriers;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Services\CourierService;
use App\Services\SteadfastCourierClient;

class SteadfastCourierAdapter extends AbstractCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_STEADFAST;
    }

    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        return app(CourierService::class)->createSteadfastBooking($order, $provider, $data);
    }

    public function sync(CourierBooking $booking): CourierBooking
    {
        return app(CourierService::class)->syncSteadfastStatus($booking);
    }

    public function balance(CourierProvider $provider): ?array
    {
        return app(SteadfastCourierClient::class)->balance($provider);
    }

    public function returns(CourierBooking $booking, array $data = []): array
    {
        return app(CourierService::class)->requestReturn($booking, $data);
    }

    public function paymentHistory(CourierProvider $provider, array $filters = []): array
    {
        return app(SteadfastCourierClient::class)->payments($provider, $filters);
    }

    public function webhookStatus(array $payload): ?string
    {
        $status = $payload['delivery_status'] ?? $payload['status'] ?? null;

        return $status ? app(CourierService::class)->normalizeSteadfastStatus((string) $status) : null;
    }

    /**
     * Steadfast's webhook panel has no concept of a signed payload — it
     * only offers a "Callback URL" and an "Auth Token (Bearer)" that it
     * echoes back verbatim as a standard `Authorization: Bearer <token>`
     * header on every call. So unlike the generic HMAC-signature check
     * this class would otherwise inherit, verification here is a plain
     * constant-time comparison against the stored token.
     */
    public function verifyWebhook(CourierProvider $provider, string $payload, ?string $signature): bool
    {
        if (($provider->settings['webhook_signature_required'] ?? true) === false) {
            return true;
        }

        $token = $provider->credentials['webhook_secret'] ?? null;
        if (blank($token) || blank($signature)) {
            return false;
        }

        $received = str_starts_with($signature, 'Bearer ') ? substr($signature, 7) : $signature;

        return hash_equals((string) $token, $received);
    }

    public function signatureHeaderDefault(): string
    {
        return 'Authorization';
    }
}
