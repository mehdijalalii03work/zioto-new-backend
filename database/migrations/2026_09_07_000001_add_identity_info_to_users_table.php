<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('father_name', 50)->nullable()->after('last_name');
            $table->string('gender', 10)->nullable()->after('father_name');
            $table->string('birth_place', 100)->nullable()->after('gender');
            $table->string('identity_verification_status', 20)->default('pending')->after('shahkar_verified');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['father_name', 'gender', 'birth_place', 'identity_verification_status', 'identity_verified_at']);
        });
    }
};
