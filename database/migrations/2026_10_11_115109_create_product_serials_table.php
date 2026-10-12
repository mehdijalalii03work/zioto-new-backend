<?php

use App\Enums\Inventory\SerialStatus;
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
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('serial_code', 50)->unique();
            $table->string('serial_prefix', 10);
            $table->foreignId('import_row_id')->nullable()->unique()->constrained('serial_import_rows')->restrictOnDelete();
            $table->string('status', 20)->default(SerialStatus::Available->value);
            $table->foreignId('current_order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('unit_number')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();

            $table->unique(['current_order_item_id', 'unit_number']);
            $table->index(['product_id', 'status', 'id']);
            $table->index(['status', 'reserved_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
