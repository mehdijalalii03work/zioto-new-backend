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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_id', 100)->nullable()->unique();
            $table->text('verification_context')->nullable();
            $table->timestamp('verification_started_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->timestamp('verification_deadline_at')->nullable();
            $table->timestamp('next_verification_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_id']);
            $table->dropColumn([
                'provider_id',
                'verification_context',
                'verification_started_at',
                'verification_attempts',
                'verification_deadline_at',
                'next_verification_at',
            ]);
        });
    }
};
