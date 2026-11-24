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
        Schema::table('rahkaran_serials', function (Blueprint $table) {
            $table->boolean('missing_from_warehouse')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rahkaran_serials', function (Blueprint $table) {
            $table->dropColumn('missing_from_warehouse');
        });
    }
};
