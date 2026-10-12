<?php

namespace App\Enums\Inventory;

enum SerialStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Allocated = 'allocated';
    case Shipped = 'shipped';
    case Returned = 'returned';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'موجود',
            self::Reserved => 'رزرو شده',
            self::Allocated => 'تخصیص یافته',
            self::Shipped => 'ارسال شده',
            self::Returned => 'مرجوعی',
            self::Damaged => 'آسیب‌دیده',
            self::Lost => 'مفقود',
        };
    }
}
