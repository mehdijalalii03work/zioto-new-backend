<?php

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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('inventory_mode', 10)->nullable();
            $table->boolean('serial_tracking_required')->default(false);
            $table->string('serial_fulfillment_status', 30)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['inventory_mode', 'serial_tracking_required', 'serial_fulfillment_status']);
        });
    }
};
