<?php

use App\Enums\Inventory\SerialFulfillmentStatus;
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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('serial_fulfillment_status', 30)->default(SerialFulfillmentStatus::NotRequired->value);
            $table->boolean('serial_inventory_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['serial_fulfillment_status', 'serial_inventory_enabled']);
        });
    }
};
