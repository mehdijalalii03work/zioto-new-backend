<?php

namespace Database\Factories;

use App\Enums\Inventory\SerialImportBatchStatus;
use App\Models\SerialImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerialImportBatch>
 */
class SerialImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_filename' => 'serials-'.fake()->date().'.xlsx',
            'file_hash' => hash('sha256', fake()->unique()->uuid()),
            'worksheet_name' => '1',
            'column_letter' => 'M',
            'header_row' => 1,
            'status' => SerialImportBatchStatus::Analyzing->value,
        ];
    }
}
