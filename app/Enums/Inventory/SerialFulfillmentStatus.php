<?php

namespace App\Enums\Inventory;

enum SerialFulfillmentStatus: string
{
    case NotRequired = 'not_required';
    case Reserved = 'reserved';
    case Allocated = 'allocated';
    case AwaitingProduction = 'awaiting_production';
    case NeedsReview = 'needs_review';
    case Shipped = 'shipped';
    case Returned = 'returned';
    case Legacy = 'legacy';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'نیازمند سریال نیست',
            self::Reserved => 'رزرو شده',
            self::Allocated => 'تخصیص یافته',
            self::AwaitingProduction => 'در انتظار تولید',
            self::NeedsReview => 'نیازمند بررسی',
            self::Shipped => 'ارسال شده',
            self::Returned => 'مرجوع شده',
            self::Legacy => 'تاریخی',
        };
    }
}
