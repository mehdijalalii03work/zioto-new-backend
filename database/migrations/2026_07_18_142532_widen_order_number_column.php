<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 20)->nullable()->change();
        });

        if (! Schema::hasIndex('orders', 'orders_order_number_unique')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unique('order_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 100)->nullable()->change();
        });

        if (Schema::hasIndex('orders', 'orders_order_number_unique')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_order_number_unique');
            });
        }
    }
};
