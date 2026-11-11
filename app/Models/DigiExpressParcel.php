<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigiExpressParcel extends Model
{
    protected $table = 'digiexpress_parcels';

    protected $fillable = [
        'order_shipping_id', 'consignor_reference_id', 'provider_parcel_id', 'parcel_code', 'weight_grams',
        'dimensions_cm', 'status', 'label_path',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'integer',
            'dimensions_cm' => 'array',
        ];
    }

    public function orderShipping(): BelongsTo
    {
        return $this->belongsTo(OrderShipping::class);
    }
}
