<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('hesabfa_physical_stock', 10, 2)->nullable()->after('stock_quantity');
            $table->decimal('hesabfa_reserved_stock', 10, 2)->nullable()->after('hesabfa_physical_stock');
            $table->decimal('hesabfa_manual_reserved', 10, 2)->nullable()->default(0)->after('hesabfa_reserved_stock');
            $table->boolean('hesabfa_exclude_from_sync')->default(false)->after('hesabfa_manual_reserved');
            $table->boolean('hesabfa_stock_locked')->default(false)->after('hesabfa_exclude_from_sync');
            $table->timestamp('hesabfa_stock_synced_at')->nullable()->after('hesabfa_stock_locked');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'hesabfa_physical_stock',
                'hesabfa_reserved_stock',
                'hesabfa_manual_reserved',
                'hesabfa_exclude_from_sync',
                'hesabfa_stock_locked',
                'hesabfa_stock_synced_at',
            ]);
        });
    }
};
