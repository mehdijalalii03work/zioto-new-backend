<?php

use App\Enums\Inventory\InventoryMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('serial_prefix', 4)->nullable()->unique()->after('sku');
            $table->string('inventory_mode', 10)->default(InventoryMode::Manual->value)->after('serial_prefix');
            $table->boolean('track_serials')->default(false)->after('inventory_mode');
            $table->boolean('made_to_order')->default(false)->after('track_serials');
            $table->string('production_lead_time_text')->nullable()->after('made_to_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['serial_prefix', 'inventory_mode', 'track_serials', 'made_to_order', 'production_lead_time_text']);
        });
    }
};
