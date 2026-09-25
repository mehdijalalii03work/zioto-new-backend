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
            $table->string('code')->unique();
            $table->string('type', 30)->default('total_percent');
            $table->decimal('value', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_user_specific')->default(false);
            $table->json('allowed_users')->nullable();
            $table->integer('usage_limit')->default(0);
            $table->integer('usage_count')->default(0);
            $table->date('expiry_date')->nullable();
            $table->decimal('min_order_amount', 14, 2)->default(0);
            $table->json('allowed_metal_types')->nullable();
            $table->json('allowed_labor_roles')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
