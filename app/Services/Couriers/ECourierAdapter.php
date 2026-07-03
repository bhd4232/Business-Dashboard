<?php

namespace App\Services\Couriers;

use App\Models\CourierProvider;

class ECourierAdapter extends PendingLiveCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_ECOURIER;
    }
}
