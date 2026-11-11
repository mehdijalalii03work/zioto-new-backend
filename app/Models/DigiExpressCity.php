<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DigiExpressCity extends Model
{
    protected $table = 'digiexpress_cities';

    protected $primaryKey = 'external_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'external_id', 'name', 'state_id', 'state_name', 'latitude', 'longitude', 'payload', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(DigiExpressCityMapping::class, 'digiexpress_city_id', 'external_id');
    }
}
