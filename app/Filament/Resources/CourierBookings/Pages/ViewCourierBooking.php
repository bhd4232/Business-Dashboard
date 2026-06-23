<?php

namespace App\Filament\Resources\CourierBookings\Pages;

use App\Filament\Resources\CourierBookings\CourierBookingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCourierBooking extends ViewRecord
{
    protected static string $resource = CourierBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CourierBookingResource::syncSteadfastAction(),
            CourierBookingResource::statusAction(),
            CourierBookingResource::trackAction(),
            CourierBookingResource::labelAction(),
            CourierBookingResource::cancelAction(),
        ];
    }
}
