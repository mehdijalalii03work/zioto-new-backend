<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_shippings', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 0)->nullable()->after('shipping_cost');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_shippings', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'tax_rate']);
        });
    }
};
