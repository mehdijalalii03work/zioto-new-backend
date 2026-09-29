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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('price_board_item')->nullable()->after('product_id');
            $table->decimal('board_price', 12, 2)->nullable()->after('price_board_item');
            $table->decimal('weight', 8, 2)->nullable()->after('board_price');
            $table->decimal('labor_coefficient', 8, 6)->nullable()->after('weight');
            $table->decimal('labor_cost', 12, 2)->nullable()->after('labor_coefficient');
            $table->json('taxes')->nullable()->after('labor_cost');
            $table->decimal('final_price', 12, 2)->nullable()->after('taxes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['price_board_item', 'board_price', 'weight', 'labor_coefficient', 'labor_cost', 'taxes', 'final_price']);
        });
    }
};
