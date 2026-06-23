<?php

namespace App\Filament\Resources\CourierBookings\Pages;

use App\Filament\Resources\CourierBookings\CourierBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListCourierBookings extends ListRecords
{
    protected static string $resource = CourierBookingResource::class;
}
