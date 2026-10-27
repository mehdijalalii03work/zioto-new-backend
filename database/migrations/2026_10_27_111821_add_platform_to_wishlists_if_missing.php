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
        if (! Schema::hasTable('wishlists') || Schema::hasColumn('wishlists', 'platform')) {
            return;
        }

        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('wishlists') || ! Schema::hasColumn('wishlists', 'platform')) {
            return;
        }

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
