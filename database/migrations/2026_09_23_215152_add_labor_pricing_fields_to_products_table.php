<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('dynamic_pricing_enabled')->default(false)->after('price_type');
            $table->json('labor_coefficients')->nullable()->after('fee_off_hours');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['dynamic_pricing_enabled', 'labor_coefficients']);
        });
    }
};
