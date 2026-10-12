<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow gram weights with up to 7 decimals (e.g. one ounce = 31.1034768g).
     * No doctrine/dbal available, so plain ALTER on MySQL; SQLite ignores
     * decimal precision and needs no change.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `products` MODIFY `weight` DECIMAL(14, 7) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `products` MODIFY `weight` DECIMAL(10, 2) NULL');
        }
    }
};
