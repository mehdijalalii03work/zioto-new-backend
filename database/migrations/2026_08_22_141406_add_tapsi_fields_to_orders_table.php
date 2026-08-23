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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tapsi_order_id', 50)->nullable()->after('id');
            $table->string('tapsi_order_number', 50)->nullable()->after('tapsi_order_id');
            $table->string('tapsi_shipment_bundle', 100)->nullable()->after('tapsi_order_number');
            $table->string('tapsi_delivery_method', 30)->nullable()->after('tapsi_shipment_bundle');
            $table->decimal('tapsi_service_fee', 12, 0)->nullable()->after('tapsi_delivery_method');
            $table->decimal('tapsi_voucher_fee', 12, 0)->nullable()->after('tapsi_service_fee');
            $table->unique('tapsi_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tapsi_order_id']);
            $table->dropColumn([
                'tapsi_order_id',
                'tapsi_order_number',
                'tapsi_shipment_bundle',
                'tapsi_delivery_method',
                'tapsi_service_fee',
                'tapsi_voucher_fee',
            ]);
        });
    }
};
