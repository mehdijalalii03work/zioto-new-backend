<?php

use App\Enums\Inventory\SerialRowResultStatus;
use App\Enums\Inventory\SerialRowValidationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: the product_serial_id foreign key is added in a later
     * migration (add_product_serial_id_fk…), after product_serials
     * exists, to avoid a circular create-table dependency.
     */
    public function up(): void
    {
        Schema::create('serial_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('serial_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->text('raw_value')->nullable();
            $table->string('normalized_serial', 50)->nullable()->index();
            $table->string('resolved_prefix', 10)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->unsignedBigInteger('product_serial_id')->nullable()->unique();
            $table->string('validation_status', 30)->default(SerialRowValidationStatus::Empty->value);
            $table->string('result_status', 30)->default(SerialRowResultStatus::Pending->value);
            $table->string('reason_code', 50)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'result_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_import_rows');
    }
};
