<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class RahkaranSettings
{
    public function get(string $key): mixed
    {
        $fallback = config("rahkaran.{$key}");
        $storedValue = setting('rahkaran_config_'.$key, null);
        if ($storedValue === null) {
            return $fallback;
        }

        if ($key === 'password') {
            try {
                return Crypt::decryptString($storedValue);
            } catch (\Throwable) {
                return $fallback;
            }
        }

        return $storedValue;
    }

    public function save(string $key, mixed $value, string $type = 'string'): void
    {
        if ($key === 'password' && blank($value)) {
            return;
        }

        $storedValue = $key === 'password'
            ? Crypt::encryptString((string) $value)
            : ($type === 'json' ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $value);

        Setting::query()->updateOrCreate(
            ['key' => 'rahkaran_config_'.$key],
            [
                'value' => $storedValue,
                'type' => $key === 'password' ? 'secret' : $type,
                'category' => 'accounting',
                'label' => 'تنظیم راهکاران: '.$key,
            ],
        );

        Cache::forget('rahkaran:session-cookie');
    }
}
