<?php

namespace App\Enums;

enum PoStatus: string
{
    case Draft           = 'draft';
    case Confirmed       = 'confirmed';
    case PartiallyShipped = 'partially_shipped';
    case Shipped         = 'shipped';
    case Received        = 'received';
    case Completed       = 'completed';
    case Cancelled       = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft            => 'Draft',
            self::Confirmed        => 'Confirmed',
            self::PartiallyShipped => 'Partially Shipped',
            self::Shipped          => 'Shipped',
            self::Received         => 'Received',
            self::Completed        => 'Completed',
            self::Cancelled        => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft            => 'purple',
            self::Confirmed        => 'blue',
            self::PartiallyShipped => 'cyan',
            self::Shipped          => 'indigo',
            self::Received         => 'emerald',
            self::Completed        => 'green',
            self::Cancelled        => 'red',
        };
    }
}
