<?php

namespace App\Enums\Product;

enum MetalType: string
{
    case Gold = 'gold';
    case Silver = 'silver';

    public function label(): string
    {
        return match ($this) {
            self::Gold => 'طلا',
            self::Silver => 'نقره',
        };
    }
}
