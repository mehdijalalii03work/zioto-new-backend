<?php

namespace Database\Factories;

use App\Enums\Inventory\SerialRowResultStatus;
use App\Enums\Inventory\SerialRowValidationStatus;
use App\Models\SerialImportBatch;
use App\Models\SerialImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerialImportRow>
 */
class SerialImportRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => SerialImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(2, 50000),
            'raw_value' => 'tst00000001',
            'normalized_serial' => 'tst00000001',
            'validation_status' => SerialRowValidationStatus::Valid->value,
            'result_status' => SerialRowResultStatus::Pending->value,
        ];
    }
}
