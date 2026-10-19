<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_ip',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
