<?php

namespace App\Services\Couriers;

use App\Models\CourierProvider;

class PathaoCourierAdapter extends PendingLiveCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_PATHAO;
    }
}
