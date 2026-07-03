<?php

namespace App\Services\Couriers;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;

abstract class PendingLiveCourierAdapter extends AbstractCourierAdapter
{
    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        $this->unsupportedLiveOperation('booking creation');
    }

    public function sync(CourierBooking $booking): CourierBooking
    {
        $this->unsupportedLiveOperation('status sync');
    }

    public function balance(CourierProvider $provider): ?array
    {
        $this->unsupportedLiveOperation('balance check');
    }

    public function verifyWebhook(CourierProvider $provider, string $payload, ?string $signature): bool
    {
        return false;
    }

    public function webhookStatus(array $payload): ?string
    {
        return null;
    }

    protected function unsupportedLiveOperation(string $operation): never
    {
        $label = CourierProvider::DRIVERS[$this->driver()] ?? str($this->driver())->headline()->toString();

        $this->unsupported("{$label} {$operation}; official API credentials, request field mapping, and sandbox/live response samples are required before enabling this live adapter");
    }
}
