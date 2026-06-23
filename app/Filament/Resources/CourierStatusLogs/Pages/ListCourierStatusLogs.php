<?php

namespace App\Filament\Resources\CourierStatusLogs\Pages;

use App\Filament\Resources\CourierStatusLogs\CourierStatusLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCourierStatusLogs extends ListRecords
{
    protected static string $resource = CourierStatusLogResource::class;
}
