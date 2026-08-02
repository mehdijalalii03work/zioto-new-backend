<?php

namespace App\Models;

use App\Support\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class Wishlist extends Model
{
    use HasTenantScope;

    protected $fillable = ['user_id', 'product_id', 'platform'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
