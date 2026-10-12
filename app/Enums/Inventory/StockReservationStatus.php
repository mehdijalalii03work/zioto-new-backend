<?php

namespace App\Enums\Inventory;

enum StockReservationStatus: string
{
    case Reserved = 'reserved';
    case Consumed = 'consumed';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'رزرو شده',
            self::Consumed => 'مصرف شده',
            self::Released => 'آزاد شده',
        };
    }
}
