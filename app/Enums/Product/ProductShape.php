<?php

namespace App\Enums\Product;

enum ProductShape: string
{
    case Shammesh = 'shammesh';
    case Saachmeh = 'saachmeh';
    case Pelak = 'pelak';

    public function label(): string
    {
        return match ($this) {
            self::Shammesh => 'شمش',
            self::Saachmeh => 'ساچمه',
            self::Pelak => 'پلاک',
        };
    }
}
