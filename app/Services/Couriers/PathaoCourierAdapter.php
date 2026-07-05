<?php

namespace App\Services\Couriers;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Services\CourierService;

class PathaoCourierAdapter extends AbstractCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_PATHAO;
    }

    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        return app(CourierService::class)->createPathaoBooking($order, $provider, $data);
    }

    public function sync(CourierBooking $booking): CourierBooking
    {
        return app(CourierService::class)->syncPathaoStatus($booking);
    }

    public function webhookStatus(array $payload): ?string
    {
        $status = $payload['order_status'] ?? $payload['status'] ?? null;

        return $status ? app(CourierService::class)->normalizePathaoStatus((string) $status) : null;
    }
}
