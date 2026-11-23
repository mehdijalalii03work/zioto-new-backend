<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RahkaranInvoice extends Model
{
    protected $fillable = ['sales_area_id', 'remote_id', 'status', 'serial_status', 'payload', 'tracking_factors', 'last_error'];

    protected function casts(): array
    {
        return [
            'sales_area_id' => 'integer',
            'remote_id' => 'integer',
            'payload' => 'array',
            'tracking_factors' => 'array',
        ];
    }
}
