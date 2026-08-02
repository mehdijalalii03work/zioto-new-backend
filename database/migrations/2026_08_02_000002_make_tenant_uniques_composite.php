<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1b: replace single-column unique indexes with composite
 * (platform, column) uniques so each tenant can hold its own
 * phone / national_code / api_token_hash / order_number / transaction_id.
 *
 * Order matters: add the composite indexes FIRST (safe on existing rows,
 * all of which are platform='main'), then drop the old single-column ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- users: phone, national_code, api_token_hash ---
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasIndex('users', 'users_platform_phone_unique')) {
                $table->unique(['platform', 'phone'], 'users_platform_phone_unique');
            }
            if (! Schema::hasIndex('users', 'users_platform_national_code_unique')) {
                $table->unique(['platform', 'national_code'], 'users_platform_national_code_unique');
            }
            if (! Schema::hasIndex('users', 'users_platform_api_token_hash_unique')) {
                $table->unique(['platform', 'api_token_hash'], 'users_platform_api_token_hash_unique');
            }
        });

        // --- orders: order_number ---
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasIndex('orders', 'orders_platform_order_number_unique')) {
                $table->unique(['platform', 'order_number'], 'orders_platform_order_number_unique');
            }
        });

        // --- payments: transaction_id ---
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasIndex('payments', 'payments_platform_transaction_id_unique')) {
                $table->unique(['platform', 'transaction_id'], 'payments_platform_transaction_id_unique');
            }
        });

        // --- now drop the old single-column uniques ---
        // (users_phone_unique, users_national_code_unique, users_api_token_hash_unique,
        //  orders_order_number_unique, payments_transaction_id_unique)
        Schema::table('users', function (Blueprint $table) {
            foreach (['users_phone_unique', 'users_national_code_unique', 'users_api_token_hash_unique'] as $index) {
                if (Schema::hasIndex('users', $index)) {
                    $table->dropUnique($index);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', 'orders_order_number_unique')) {
                $table->dropUnique('orders_order_number_unique');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasIndex('payments', 'payments_transaction_id_unique')) {
                $table->dropUnique('payments_transaction_id_unique');
            }
        });
    }

    public function down(): void
    {
        // Re-add the old single-column uniques
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
            $table->unique('national_code');
            $table->unique('api_token_hash');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transaction_id');
        });

        // Drop the composite ones
        Schema::table('users', function (Blueprint $table) {
            foreach (['users_platform_phone_unique', 'users_platform_national_code_unique', 'users_platform_api_token_hash_unique'] as $index) {
                if (Schema::hasIndex('users', $index)) {
                    $table->dropUnique($index);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', 'orders_platform_order_number_unique')) {
                $table->dropUnique('orders_platform_order_number_unique');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasIndex('payments', 'payments_platform_transaction_id_unique')) {
                $table->dropUnique('payments_platform_transaction_id_unique');
            }
        });
    }
};
