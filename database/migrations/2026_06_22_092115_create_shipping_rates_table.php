<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('shipping_method_id')->unsigned()->constrained()->cascadeOnDelete();
            $table->enum('rate_type', ['flat', 'province', 'city', 'weight', 'cart_total']);
            $table->tinyInteger('province_id')->unsigned()->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('min_weight', 10, 3)->nullable();
            $table->decimal('max_weight', 10, 3)->nullable();
            $table->decimal('min_cart_total', 12, 0)->nullable();
            $table->decimal('max_cart_total', 12, 0)->nullable();
            $table->decimal('base_rate', 12, 0);
            $table->decimal('per_kg_rate', 12, 0)->nullable();
            $table->decimal('free_shipping_min', 12, 0)->nullable();
            $table->tinyInteger('estimated_days_min')->nullable();
            $table->tinyInteger('estimated_days_max')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
