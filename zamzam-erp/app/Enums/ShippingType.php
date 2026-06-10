<?php

namespace App\Enums;

enum ShippingType: string
{
    case Sea     = 'sea';
    case Air     = 'air';
    case Rail    = 'rail';
    case Courier = 'courier';

    public function label(): string
    {
        return match($this) {
            self::Sea     => 'Sea Freight',
            self::Air     => 'Air Freight',
            self::Rail    => 'Rail Freight',
            self::Courier => 'Courier',
        };
    }
}
