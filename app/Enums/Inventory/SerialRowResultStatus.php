<?php

namespace App\Enums\Inventory;

enum SerialRowResultStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case DuplicateInDb = 'duplicate_in_db';
    case Rejected = 'rejected';
    case Empty = 'empty';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Imported => 'وارد شده',
            self::DuplicateInDb => 'تکراری در سامانه',
            self::Rejected => 'رد شده',
            self::Empty => 'خالی',
        };
    }
}
