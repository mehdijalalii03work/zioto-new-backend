<?php

namespace App\Models;

use App\Enums\Inventory\SerialImportBatchStatus;
use Database\Factories\SerialImportBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerialImportBatch extends Model
{
    /** @use HasFactory<SerialImportBatchFactory> */
    use HasFactory;

    protected static function newFactory(): SerialImportBatchFactory
    {
        return SerialImportBatchFactory::new();
    }

    protected $fillable = [
        'original_filename',
        'file_path',
        'file_hash',
        'worksheet_name',
        'column_letter',
        'header_row',
        'parser_version',
        'mapping_snapshot',
        'scanned_rows',
        'trailing_empty_rows',
        'created_by',
        'status',
        'started_at',
        'completed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => SerialImportBatchStatus::class,
            'mapping_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SerialImportRow::class, 'import_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
