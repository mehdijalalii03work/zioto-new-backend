<?php

namespace App\Enums\Inventory;

enum InventoryMode: string
{
    case Serial = 'serial';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Serial => 'سریالی',
            self::Manual => 'دستی / تولید پس از سفارش',
        };
    }
}
