<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_id', 50)->unique();
            $table->decimal('amount', 12, 0)->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 50)->nullable();
            $table->json('gateway_response')->nullable();
            $table->text('description')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
