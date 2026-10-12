<?php

namespace App\Models;

use App\Enums\Inventory\SerialRowResultStatus;
use App\Enums\Inventory\SerialRowValidationStatus;
use Database\Factories\SerialImportRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Product\Models\Product;

class SerialImportRow extends Model
{
    /** @use HasFactory<SerialImportRowFactory> */
    use HasFactory;

    protected static function newFactory(): SerialImportRowFactory
    {
        return SerialImportRowFactory::new();
    }

    protected $fillable = [
        'import_batch_id',
        'row_number',
        'raw_value',
        'normalized_serial',
        'resolved_prefix',
        'product_id',
        'product_serial_id',
        'validation_status',
        'result_status',
        'reason_code',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_status' => SerialRowValidationStatus::class,
            'result_status' => SerialRowResultStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SerialImportBatch::class, 'import_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSerial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }

    public function createdSerial(): HasOne
    {
        return $this->hasOne(ProductSerial::class, 'import_row_id');
    }
}
