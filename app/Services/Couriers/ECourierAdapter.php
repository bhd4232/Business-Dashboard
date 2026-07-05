<?php

namespace App\Services\Couriers;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Services\CourierService;

class ECourierAdapter extends AbstractCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_ECOURIER;
    }

    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        return app(CourierService::class)->createECourierBooking($order, $provider, $data);
    }

    public function sync(CourierBooking $booking): CourierBooking
    {
        return app(CourierService::class)->syncECourierStatus($booking);
    }

    public function webhookStatus(array $payload): ?string
    {
        $status = $payload['status'] ?? $payload['delivery_status'] ?? null;

        return $status ? app(CourierService::class)->normalizeECourierStatus((string) $status) : null;
    }
}
