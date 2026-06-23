<?php

namespace App\Contracts;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;

interface CourierProviderInterface
{
    public function driver(): string;

    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking;

    public function sync(CourierBooking $booking): CourierBooking;

    public function cancel(CourierBooking $booking): CourierBooking;

    public function trackingUrl(CourierBooking $booking): ?string;

    public function labelUrl(CourierBooking $booking): ?string;

    public function balance(CourierProvider $provider): ?array;

    public function verifyWebhook(CourierProvider $provider, string $payload, ?string $signature): bool;

    public function webhookStatus(array $payload): ?string;
}
