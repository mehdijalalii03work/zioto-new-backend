<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of tenant separation: adds a `platform` column to every
 * user-scoped table so the nopay landing (pay.sawiss.com) can have its
 * own isolated users, carts, orders, addresses, wishlists and payments.
 *
 * Strategy (safe ordering):
 *   1. Add `platform` (default 'main') — existing rows stay on 'main'.
 *   2. Add composite unique indexes (platform, ...) — safe on current data.
 *   3. Drop the old single-column unique indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. users ---
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });

        // --- 2. carts ---
        Schema::table('carts', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });

        // --- 3. wishlists ---
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });

        // --- 4. user_addresses ---
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });

        // --- 5. orders ---
        Schema::table('orders', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });

        // --- 6. payments ---
        Schema::table('payments', function (Blueprint $table) {
            $table->string('platform', 20)->default('main')->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('platform'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('platform'));
        Schema::table('user_addresses', fn (Blueprint $table) => $table->dropColumn('platform'));
        Schema::table('wishlists', fn (Blueprint $table) => $table->dropColumn('platform'));
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('platform'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('platform'));
    }
};
