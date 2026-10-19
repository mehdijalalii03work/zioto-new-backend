<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_audit_logs', function (Blueprint $table) {
            $table->string('actor_ip', 45)->nullable()->after('actor_name');
        });
    }

    public function down(): void
    {
        Schema::table('price_audit_logs', function (Blueprint $table) {
            $table->dropColumn('actor_ip');
        });
    }
};
