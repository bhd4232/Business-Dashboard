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

    public function webhookStatus(array $payload): ?string
    {
        $status = $payload['delivery_status'] ?? $payload['status'] ?? null;

        return $status ? app(CourierService::class)->normalizeSteadfastStatus((string) $status) : null;
    }
}
