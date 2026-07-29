<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->tinyInteger('province_id')->unsigned()->nullable()->change();
            $table->foreignId('city_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->tinyInteger('province_id')->unsigned()->nullable(false)->change();
            $table->foreignId('city_id')->nullable(false)->change();
        });
    }
};
