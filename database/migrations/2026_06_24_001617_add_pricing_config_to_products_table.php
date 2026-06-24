<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereNotNull('weight')
            ->update(['weight' => DB::raw('weight * 1000')]);

        Schema::table('products', function (Blueprint $table) {
            $table->string('price_board_item', 50)->nullable()->after('weight');
            $table->decimal('fee_off_hours', 5, 2)->default(0)->after('price_board_item');
            $table->decimal('fee_business_hours', 5, 2)->default(0)->after('fee_off_hours');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_board_item', 'fee_off_hours', 'fee_business_hours']);
        });

        DB::table('products')
            ->whereNotNull('weight')
            ->update(['weight' => DB::raw('weight / 1000')]);
    }
};
