<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('hesabfa_contact_code', 50)->nullable()->after('notes');
            $table->unsignedBigInteger('hesabfa_invoice_number')->nullable()->after('hesabfa_contact_code');
            $table->string('hesabfa_invoice_reference', 50)->nullable()->after('hesabfa_invoice_number');
            $table->timestamp('hesabfa_synced_at')->nullable()->after('hesabfa_invoice_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'hesabfa_contact_code',
                'hesabfa_invoice_number',
                'hesabfa_invoice_reference',
                'hesabfa_synced_at',
            ]);
        });
    }
};
