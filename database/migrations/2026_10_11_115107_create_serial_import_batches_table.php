<?php

use App\Enums\Inventory\SerialImportBatchStatus;
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
        Schema::create('serial_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_path')->nullable();
            $table->char('file_hash', 64)->index();
            $table->string('worksheet_name')->nullable();
            $table->string('column_letter', 2)->default('M');
            $table->unsignedInteger('header_row')->default(1);
            $table->string('parser_version', 20)->default('v1');
            $table->json('mapping_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default(SerialImportBatchStatus::Analyzing->value)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_import_batches');
    }
};
