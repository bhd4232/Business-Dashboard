<?php

namespace App\Enums;

enum CostAllocationMethod: string
{
    case Weight   = 'weight';
    case Volume   = 'volume';
    case Value    = 'value';
    case Quantity = 'quantity';
    case Manual   = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Weight   => 'By Weight (kg)',
            self::Volume   => 'By Volume (CBM)',
            self::Value    => 'By Purchase Value',
            self::Quantity => 'By Quantity',
            self::Manual   => 'Manual Allocation',
        };
    }
}
