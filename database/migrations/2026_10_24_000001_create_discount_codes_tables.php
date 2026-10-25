<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->unsignedBigInteger('discount_amount');
            $table->json('platforms');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_code_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['discount_code_id', 'user_id']);
            $table->unique(['discount_code_id', 'order_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('discount_code_snapshot', 64)->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn(['discount_code_snapshot', 'discount_amount']);
        });

        Schema::dropIfExists('discount_code_usages');
        Schema::dropIfExists('discount_codes');
    }
};
