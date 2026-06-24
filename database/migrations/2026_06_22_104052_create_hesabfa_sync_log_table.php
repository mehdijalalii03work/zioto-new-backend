<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hesabfa_sync_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sync_type', 50);
            $table->string('status', 20);
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('sync_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hesabfa_sync_log');
    }
};
