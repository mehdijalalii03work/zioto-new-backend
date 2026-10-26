<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SmsIrApiLog extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'sms_ir_api_logs';

    protected $fillable = [
        'endpoint',
        'method',
        'request_body',
        'response_status',
        'response_body',
        'duration_ms',
        'success',
        'error_message',
        'mobile',
        'error_code',
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
}
