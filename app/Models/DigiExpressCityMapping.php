<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigiExpressCityMapping extends Model
{
    protected $table = 'digiexpress_city_mappings';

    protected $fillable = [
        'city_id', 'digiexpress_city_id', 'suggested_city_id', 'is_verified', 'is_destination_enabled', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_destination_enabled' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function digiexpressCity(): BelongsTo
    {
        return $this->belongsTo(DigiExpressCity::class, 'digiexpress_city_id', 'external_id');
    }

    public function suggestedCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'suggested_city_id');
    }
}
