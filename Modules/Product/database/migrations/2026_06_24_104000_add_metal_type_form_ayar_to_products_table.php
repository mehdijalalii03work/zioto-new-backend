<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('metal_type')->nullable()->after('description');
            $table->string('form')->nullable()->after('metal_type');
            $table->string('ayar')->nullable()->after('form');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['metal_type', 'form', 'ayar']);
        });
    }
};
