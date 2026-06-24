<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\Order;

class HesabfaSyncLog extends Model
{
    protected $table = 'hesabfa_sync_log';

    protected $fillable = [
        'order_id',
        'sync_type',
        'status',
        'request_data',
        'response_data',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
