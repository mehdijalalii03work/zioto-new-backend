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
            $table->string('tapsi_order_status_id', 10)->nullable()->index();
            $table->timestamp('tapsi_synced_at')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('tapsi_order_item_id', 50)->nullable()->unique();
        });

        Schema::create('tapsi_webhook_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 50)->unique();
            $table->string('tapsi_order_id', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tapsi_webhook_requests');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['tapsi_order_item_id']);
            $table->dropColumn('tapsi_order_item_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tapsi_order_status_id']);
            $table->dropColumn(['tapsi_order_status_id', 'tapsi_synced_at']);
        });
    }
};
