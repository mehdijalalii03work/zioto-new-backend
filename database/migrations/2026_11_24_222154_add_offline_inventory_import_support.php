<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('rahkaran_item_code', 100)->nullable()->unique();
        });

        Schema::table('rahkaran_serials', function (Blueprint $table): void {
            $table->string('origin', 10)->default('api');
        });

        Schema::create('offline_inventory_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('document_number', 100)->unique();
            $table->char('fingerprint', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('summary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_inventory_receipts');
        Schema::table('rahkaran_serials', fn (Blueprint $table) => $table->dropColumn('origin'));
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['rahkaran_item_code']);
            $table->dropColumn('rahkaran_item_code');
        });
    }
};
