<?php

namespace App\Services\Couriers;

use App\Contracts\CourierProviderInterface;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Services\CourierService;
use Illuminate\Validation\ValidationException;

abstract class AbstractCourierAdapter implements CourierProviderInterface
{
    public function cancel(CourierBooking $booking): CourierBooking
    {
        return app(CourierService::class)->updateStatus(
            $booking,
            CourierBooking::STATUS_CANCELLED,
            'Courier booking cancelled from ERP.',
        );
    }

    public function trackingUrl(CourierBooking $booking): ?string
    {
        $template = $booking->provider?->settings['tracking_url'] ?? null;

        return $template && $booking->tracking_id
            ? str_replace('{tracking_id}', rawurlencode($booking->tracking_id), $template)
            : null;
    }

    public function labelUrl(CourierBooking $booking): ?string
    {
        $template = $booking->provider?->settings['label_url'] ?? null;

        return $template
            ? str_replace(['{tracking_id}', '{reference}'], [rawurlencode((string) $booking->tracking_id), rawurlencode((string) $booking->provider_reference)], $template)
            : null;
    }

    public function balance(CourierProvider $provider): ?array
    {
        return null;
    }

    public function verifyWebhook(CourierProvider $provider, string $payload, ?string $signature): bool
    {
        $secret = $provider->credentials['webhook_secret'] ?? null;

        return filled($secret) && filled($signature)
            && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    protected function unsupported(string $operation): never
    {
        throw ValidationException::withMessages(['courier' => "The {$operation} operation is not supported by this courier provider."]);
    }
}
