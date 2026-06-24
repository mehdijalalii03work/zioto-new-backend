<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'category',
        'label',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'number' => is_numeric($setting->value) ? (float) $setting->value : $setting->value,
                'integer' => (int) $setting->value,
                'json' => json_decode($setting->value, true),
                default => (string) $setting->value,
            };
        });
    }

    public static function clearCache(string $key): void
    {
        Cache::forget("setting:{$key}");
    }

    protected static function booted(): void
    {
        static::saved(fn ($setting) => static::clearCache($setting->key));
        static::deleted(fn ($setting) => static::clearCache($setting->key));
    }
}
