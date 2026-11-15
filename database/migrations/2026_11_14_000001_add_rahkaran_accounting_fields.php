<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('rahkaran_physical_stock')->nullable();
            $table->unsignedInteger('rahkaran_reserved_stock')->default(0);
            $table->boolean('rahkaran_serial_tracked')->default(false);
        });

        Schema::table('user_addresses', function (Blueprint $table): void {
            $table->unsignedBigInteger('rahkaran_address_id')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('rahkaran_customer_id')->nullable();
            $table->unsignedBigInteger('rahkaran_invoice_id')->nullable();
            $table->timestamp('rahkaran_synced_at')->nullable();
            $table->string('rahkaran_sync_status', 20)->nullable();
            $table->text('rahkaran_sync_error')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('rahkaran_stock_reserved_quantity')->default(0);
            $table->boolean('rahkaran_stock_consumed')->default(false);
        });

        Schema::create('rahkaran_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('serial', 150)->unique();
            $table->string('status', 20)->default('available')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rahkaran_serials');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['rahkaran_customer_id', 'rahkaran_invoice_id', 'rahkaran_synced_at', 'rahkaran_sync_status', 'rahkaran_sync_error']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['rahkaran_stock_reserved_quantity', 'rahkaran_stock_consumed']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['rahkaran_physical_stock', 'rahkaran_reserved_stock', 'rahkaran_serial_tracked']);
        });

        Schema::table('user_addresses', function (Blueprint $table): void {
            $table->dropColumn('rahkaran_address_id');
        });
    }
};
