<?php

namespace App\Enums\Inventory;

enum SerialEventType: string
{
    case Imported = 'imported';
    case Reserved = 'reserved';
    case Allocated = 'allocated';
    case ReservationReleased = 'reservation_released';
    case AllocationReleased = 'allocation_released';
    case Shipped = 'shipped';
    case Returned = 'returned';
    case Restocked = 'restocked';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Imported => 'ورود به انبار',
            self::Reserved => 'رزرو',
            self::Allocated => 'تخصیص نهایی',
            self::ReservationReleased => 'آزادسازی رزرو',
            self::AllocationReleased => 'آزادسازی تخصیص',
            self::Shipped => 'خروج فیزیکی',
            self::Returned => 'دریافت مرجوعی',
            self::Restocked => 'بازگشت به موجودی',
            self::Damaged => 'آسیب',
            self::Lost => 'مفقودی',
        };
    }
}
