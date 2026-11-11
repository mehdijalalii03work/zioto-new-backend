<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'name',
        'slug',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function digiExpressMapping(): HasOne
    {
        return $this->hasOne(DigiExpressCityMapping::class);
    }
}
