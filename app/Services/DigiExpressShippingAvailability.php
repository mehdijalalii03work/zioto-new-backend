<?php

namespace App\Services;

use App\Models\City;
use App\Models\DigiExpressCityMapping;
use App\Models\Province;
use App\Models\ShippingMethod;

class DigiExpressShippingAvailability
{
    public function isAvailable(ShippingMethod $method, ?int $cityId, ?int $provinceId): bool
    {
        if (! $method->is_active) {
            return false;
        }

        if ($method->code === 'in_store_pickup') {
            return true;
        }

        if (in_array($method->code, ['tipax', 'post_precious'], true)) {
            return false;
        }

        $city = $cityId ? City::with('province')->find($cityId) : null;
        $province = $city?->province ?? ($provinceId ? Province::find($provinceId) : null);
        $isTehranProvince = $province?->slug === 'tehran';

        if ($method->code === 'peyk') {
            return $isTehranProvince && ($city?->slug === 'tehran' || $city?->name === 'تهران');
        }

        if ($method->code === 'digiexpress_address') {
            if ($isTehranProvince || ! $city) {
                return false;
            }

            return DigiExpressCityMapping::query()
                ->where('city_id', $city->id)
                ->where('is_verified', true)
                ->where('is_destination_enabled', true)
                ->whereHas('digiexpressCity')
                ->exists();
        }

        return true;
    }
}
