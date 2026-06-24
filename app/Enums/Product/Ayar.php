<?php

namespace App\Enums\Product;

enum Ayar: string
{
    case P995 = '995';
    case P999 = '999';
    case P9999 = '9999';

    public function label(): string
    {
        return match ($this) {
            self::P995 => '۹۹۵',
            self::P999 => '۹۹۹',
            self::P9999 => '۹۹۹۹',
        };
    }
}
