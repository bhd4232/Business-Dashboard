<?php

namespace App\Enums;

enum ShipmentCostType: string
{
    case Freight    = 'freight';
    case Duty       = 'customs_duty';
    case Vat        = 'vat';
    case Ait        = 'ait';
    case Labour     = 'labour';
    case Transport  = 'transport';
    case CustomsFee = 'customs_fee';
    case Demurrage  = 'demurrage';
    case Other      = 'other';

    public function label(): string
    {
        return match($this) {
            self::Freight    => 'Freight Charge',
            self::Duty       => 'Customs Duty',
            self::Vat        => 'VAT',
            self::Ait        => 'AIT',
            self::Labour     => 'Labour Cost',
            self::Transport  => 'Transport Cost',
            self::CustomsFee => 'Customs Fee',
            self::Demurrage  => 'Demurrage',
            self::Other      => 'Other',
        };
    }
}
