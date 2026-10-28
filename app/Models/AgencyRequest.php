<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyRequest extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'email',
        'phone',
        'mobile',
        'father_name',
        'father_job',
        'gender',
        'marital_status',
        'agency_province_id',
        'agency_city_id',
        'residence_province_id',
        'residence_city_id',
        'neighborhood',
        'address',
        'status',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function agencyProvince(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'agency_province_id');
    }

    public function agencyCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'agency_city_id');
    }

    public function residenceProvince(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'residence_province_id');
    }

    public function residenceCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'residence_city_id');
    }
}
