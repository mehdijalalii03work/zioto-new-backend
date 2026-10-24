<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_code_usages', function (Blueprint $table) {
            $table->string('status', 16)->default('reserved')->after('order_id');
        });

        DB::table('discount_code_usages')
            ->whereIn('order_id', DB::table('orders')->select('id')->where('payment_status', 'paid'))
            ->update(['status' => 'redeemed']);

        DB::table('discount_code_usages')
            ->whereIn('order_id', DB::table('orders')
                ->select('id')
                ->whereIn('status', ['cancelled', 'expired'])
                ->where('payment_status', '!=', 'paid'))
            ->update(['status' => 'released']);

        DB::table('discount_code_usages')
            ->whereNull('order_id')
            ->update(['status' => 'released']);
    }

    public function down(): void
    {
        Schema::table('discount_code_usages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
