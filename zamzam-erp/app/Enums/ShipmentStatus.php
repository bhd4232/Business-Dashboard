<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Booked               = 'booked';
    case Loaded               = 'loaded';
    case Departed             = 'departed';
    case InTransit            = 'in_transit';
    case Arrived              = 'arrived';
    case Clearing             = 'clearing';
    case Cleared              = 'cleared';
    case DeliveredToWarehouse = 'delivered_to_warehouse';

    public function label(): string
    {
        return match($this) {
            self::Booked               => 'Booked',
            self::Loaded               => 'Loaded',
            self::Departed             => 'Departed',
            self::InTransit            => 'In Transit',
            self::Arrived              => 'Arrived',
            self::Clearing             => 'Customs Clearing',
            self::Cleared              => 'Customs Cleared',
            self::DeliveredToWarehouse => 'Delivered to Warehouse',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Booked               => 'purple',
            self::Loaded               => 'blue',
            self::Departed             => 'indigo',
            self::InTransit            => 'cyan',
            self::Arrived              => 'amber',
            self::Clearing             => 'orange',
            self::Cleared              => 'teal',
            self::DeliveredToWarehouse => 'emerald',
        };
    }

    public function nextStatus(): ?self
    {
        return match($this) {
            self::Booked               => self::Loaded,
            self::Loaded               => self::Departed,
            self::Departed             => self::InTransit,
            self::InTransit            => self::Arrived,
            self::Arrived              => self::Clearing,
            self::Clearing             => self::Cleared,
            self::Cleared              => self::DeliveredToWarehouse,
            self::DeliveredToWarehouse => null,
        };
    }
}
