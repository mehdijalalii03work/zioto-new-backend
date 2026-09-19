<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class JibitApiLog extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'jibit_api_logs';

    protected $fillable = [
        'endpoint',
        'method',
        'request_body',
        'response_status',
        'response_body',
        'duration_ms',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'duration_ms' => 'integer',
            'response_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }
}
