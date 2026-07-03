<?php

namespace App\Services\Couriers;

use App\Models\CourierProvider;

class RedxCourierAdapter extends PendingLiveCourierAdapter
{
    public function driver(): string
    {
        return CourierProvider::DRIVER_REDX;
    }
}
