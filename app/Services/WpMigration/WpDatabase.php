<?php

namespace App\Services\WpMigration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WpDatabase
{
    protected string $connection = 'wp_data';

    public function table(string $table)
    {
        return DB::connection($this->connection)->table($table);
    }

    public function getMeta(int $objectId, string $metaKey, string $table = 'postmeta'): ?string
    {
        $key = $this->resolveMetaKey($table);

        $row = $this->table($table)
            ->where($key, $objectId)
            ->where('meta_key', $metaKey)
            ->first();

        return $row?->meta_value;
    }

    public function getAllMeta(int $objectId, string $table = 'postmeta'): array
    {
        $key = $this->resolveMetaKey($table);

        return $this->table($table)
            ->where($key, $objectId)
            ->get()
            ->keyBy('meta_key')
            ->map(fn ($item) => $item->meta_value)
            ->toArray();
    }

    protected function resolveMetaKey(string $table): string
    {
        return match ($table) {
            'usermeta' => 'user_id',
            'wc_orders_meta' => 'order_id',
            'woocommerce_order_itemmeta' => 'order_item_id',
            default => 'post_id',
        };
    }

    public function ensureMappingTable(string $table, array $columns): void
    {
        if (DB::connection($this->connection)->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $prefix = DB::connection($this->connection)->getTablePrefix();
        $fullTable = $prefix.$table;

        $parts = [];
        foreach ($columns as $key => $value) {
            if (is_string($key)) {
                $parts[] = "$key $value";
            } else {
                $parts[] = $value;
            }
        }

        $cols = implode(', ', $parts);

        DB::connection($this->connection)->statement(
            "CREATE TABLE $fullTable ($cols) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function saveMapping(string $table, array $data): void
    {
        try {
            $this->table($table)->updateOrInsert(
                $this->getPkCondition($table, $data),
                $data
            );
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return;
            }
            throw $e;
        }
    }

    protected function getPkCondition(string $table, array $data): array
    {
        $pk = match ($table) {
            'product_sku_map' => 'wp_product_id',
            'user_mapping' => 'wp_user_id',
            'order_mapping' => 'wp_order_id',
            default => 'id',
        };

        return [$pk => $data[$pk]];
    }
}
