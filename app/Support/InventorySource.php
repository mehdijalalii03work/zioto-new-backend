<?php

namespace App\Support;

final class InventorySource
{
    public const SYNC_LOCK = 'rahkaran:inventory-sync';

    public static function isOffline(): bool
    {
        return setting('inventory_source', 'hesabfa') === 'offline';
    }

    public static function usesRahkaranInventory(): bool
    {
        return in_array(setting('inventory_source', 'hesabfa'), ['rahkaran', 'offline'], true);
    }
}
