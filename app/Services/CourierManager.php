<?php

namespace App\Services;

use App\Contracts\CourierProviderInterface;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Services\Couriers\ECourierAdapter;
use App\Services\Couriers\ManualCourierAdapter;
use App\Services\Couriers\PathaoCourierAdapter;
use App\Services\Couriers\RedxCourierAdapter;
use App\Services\Couriers\SteadfastCourierAdapter;
use Illuminate\Validation\ValidationException;

class CourierManager
{
    /** @var array<string, class-string<CourierProviderInterface>> */
    protected array $adapters = [
        CourierProvider::DRIVER_MANUAL => ManualCourierAdapter::class,
        CourierProvider::DRIVER_STEADFAST => SteadfastCourierAdapter::class,
        CourierProvider::DRIVER_PATHAO => PathaoCourierAdapter::class,
        CourierProvider::DRIVER_REDX => RedxCourierAdapter::class,
        CourierProvider::DRIVER_ECOURIER => ECourierAdapter::class,
    ];

    public function adapter(CourierProvider|string $provider): CourierProviderInterface
    {
        $driver = $provider instanceof CourierProvider ? $provider->driver : $provider;
        $adapter = $this->adapters[$driver] ?? null;
        if (! $adapter) {
            throw ValidationException::withMessages(['provider' => "The {$driver} live adapter is not configured."]);
        }

        return app($adapter);
    }

    public function create(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        $this->assertSameCompany($order, $provider);

        return $this->adapter($provider)->create($order, $provider, $data);
    }

    public function sync(CourierBooking $booking): CourierBooking
    {
        $booking->loadMissing('provider');

        return $this->adapter($booking->provider)->sync($booking);
    }

    public function cancel(CourierBooking $booking): CourierBooking
    {
        $booking->loadMissing('provider');

        return $this->adapter($booking->provider)->cancel($booking);
    }

    protected function assertSameCompany(Order $order, CourierProvider $provider): void
    {
        if ((int) $order->company_id !== (int) $provider->company_id) {
            throw ValidationException::withMessages(['provider' => 'The courier provider must belong to the same company as the order.']);
        }
    }
}
