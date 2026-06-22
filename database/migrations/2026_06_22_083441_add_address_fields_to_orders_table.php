<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_address_id')->nullable()->after('shipping_address')->constrained()->nullOnDelete();
            $table->text('shipping_address_snapshot')->nullable()->after('user_address_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_address_id']);
            $table->dropColumn(['user_address_id', 'shipping_address_snapshot']);
        });
    }
};
