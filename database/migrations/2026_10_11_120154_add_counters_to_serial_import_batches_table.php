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
        Schema::table('serial_import_batches', function (Blueprint $table) {
            $table->unsignedInteger('scanned_rows')->default(0)->after('mapping_snapshot');
            $table->unsignedInteger('trailing_empty_rows')->default(0)->after('scanned_rows');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('serial_import_batches', function (Blueprint $table) {
            $table->dropColumn(['scanned_rows', 'trailing_empty_rows']);
        });
    }
};
