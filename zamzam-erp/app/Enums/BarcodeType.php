<?php

namespace App\Enums;

enum BarcodeType: string
{
    case Ean13   = 'ean13';
    case Code128 = 'code128';
    case Qr      = 'qr';
    case Custom  = 'custom';

    public function label(): string
    {
        return match($this) {
            self::Ean13   => 'EAN-13',
            self::Code128 => 'Code 128',
            self::Qr      => 'QR Code',
            self::Custom  => 'Custom',
        };
    }
}
