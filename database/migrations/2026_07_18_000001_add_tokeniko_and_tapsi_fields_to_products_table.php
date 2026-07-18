<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tokeniko_sku', 50)->nullable()->after('sku');
            $table->string('tapsi_product_id', 50)->nullable()->after('tokeniko_sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tokeniko_sku', 'tapsi_product_id']);
        });
    }
};
