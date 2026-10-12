<?php

namespace App\Enums\Inventory;

enum SerialRowValidationStatus: string
{
    case Valid = 'valid';
    case Empty = 'empty';
    case DuplicateInFile = 'duplicate_in_file';
    case InvalidFormat = 'invalid_format';
    case UnknownPrefix = 'unknown_prefix';
    case ProductNotTracked = 'product_not_tracked';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'معتبر',
            self::Empty => 'خالی',
            self::DuplicateInFile => 'تکراری در فایل',
            self::InvalidFormat => 'قالب نامعتبر',
            self::UnknownPrefix => 'پیشوند ناشناخته',
            self::ProductNotTracked => 'محصول غیرسریالی',
        };
    }
}
