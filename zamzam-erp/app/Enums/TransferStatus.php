<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Pending   = 'pending';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::InTransit => 'In Transit',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending   => 'amber',
            self::InTransit => 'blue',
            self::Completed => 'emerald',
            self::Cancelled => 'red',
        };
    }
}
