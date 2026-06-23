<?php

namespace App\Services\Couriers;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Services\CourierService;

class ManualCourierAdapter extends AbstractCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_MANUAL;
    }

    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        return app(CourierService::class)->createManualBooking($order, [
            ...$data,
            'courier_provider_id' => $provider->getKey(),
        ]);
    }

    public function sync(CourierBooking $booking): CourierBooking
    {
        return $booking->refresh();
    }

    public function webhookStatus(array $payload): ?string
    {
        return $payload['status'] ?? null;
    }
}
