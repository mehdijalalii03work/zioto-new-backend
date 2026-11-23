<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rahkaran_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sales_area_id');
            $table->unsignedBigInteger('remote_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('serial_status')->default('pending');
            $table->json('payload');
            $table->json('tracking_factors');
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'sales_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rahkaran_invoices');
    }
};
