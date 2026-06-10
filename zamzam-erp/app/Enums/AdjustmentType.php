<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case Add        = 'add';
    case Remove     = 'remove';
    case Correction = 'correction';

    public function label(): string
    {
        return match($this) {
            self::Add        => 'Increase',
            self::Remove     => 'Decrease',
            self::Correction => 'Correction',
        };
    }
}
