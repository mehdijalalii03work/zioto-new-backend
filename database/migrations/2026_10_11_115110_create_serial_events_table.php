<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only history: no updated_at column.
     */
    public function up(): void
    {
        Schema::create('serial_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_serial_id')->constrained('product_serials')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('unit_number')->nullable();
            $table->string('event_type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('operation_key', 100)->unique();
            $table->string('order_number_snapshot', 50)->nullable();
            $table->string('platform_snapshot', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_events');
    }
};
