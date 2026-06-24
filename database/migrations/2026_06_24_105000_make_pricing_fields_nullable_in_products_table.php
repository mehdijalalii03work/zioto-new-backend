<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->decimal('fee_off_hours', 5, 2)->nullable()->change();
            $table->decimal('fee_business_hours', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable(false)->change();
            $table->decimal('fee_off_hours', 5, 2)->nullable(false)->default(0)->change();
            $table->decimal('fee_business_hours', 5, 2)->nullable(false)->default(0)->change();
        });
    }
};
