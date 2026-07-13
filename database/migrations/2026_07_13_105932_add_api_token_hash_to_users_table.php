<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->unique()->after('api_token');
        });

        DB::table('users')
            ->whereNotNull('api_token')
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['api_token_hash' => hash('sha256', $user->api_token)]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_token_hash');
        });
    }
};
