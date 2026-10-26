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
        Schema::table('order_shippings', function (Blueprint $table) {
            $table->dateTime('order_confirmation_sms_sent_at')->nullable();
            $table->dateTime('received_health_sms_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_shippings', function (Blueprint $table) {
            $table->dropColumn(['order_confirmation_sms_sent_at', 'received_health_sms_sent_at']);
        });
    }
};
