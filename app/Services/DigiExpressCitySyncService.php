<?php

namespace App\Services;

use App\Models\City;
use App\Models\DigiExpressCity;
use App\Models\DigiExpressCityMapping;

class DigiExpressCitySyncService
{
    public function __construct(private readonly DigiExpressService $digiExpress) {}

    public function sync(): int
    {
        $localCities = City::query()->with('province')->get();
        $syncedAt = now();
        $remoteCities = $this->digiExpress->getCities();

        foreach ($remoteCities as $remoteCity) {
            if (! isset($remoteCity['id'], $remoteCity['name'])) {
                continue;
            }

            $externalId = (string) $remoteCity['id'];

            DigiExpressCity::query()->updateOrCreate(
                ['external_id' => $externalId],
                [
                    'name' => (string) $remoteCity['name'],
                    'state_id' => isset($remoteCity['state_id']) ? (string) $remoteCity['state_id'] : null,
                    'state_name' => $remoteCity['state_name'] ?? null,
                    'latitude' => $remoteCity['latitude'] ?? null,
                    'longitude' => $remoteCity['longitude'] ?? null,
                    'payload' => $remoteCity,
                    'synced_at' => $syncedAt,
                ],
            );

            DigiExpressCityMapping::query()->firstOrCreate(
                ['digiexpress_city_id' => $externalId],
                ['suggested_city_id' => $this->suggestLocalCity($localCities, $remoteCity)?->id],
            );
        }

        return count($remoteCities);
    }

    private function suggestLocalCity(iterable $localCities, array $remoteCity): ?City
    {
        $remoteName = $this->normalize((string) $remoteCity['name']);
        $remoteState = $this->normalize((string) ($remoteCity['state_name'] ?? ''));

        if ($remoteName === '' || $remoteState === '') {
            return null;
        }

        $matches = collect($localCities)->filter(fn (City $city): bool => $this->normalize($city->name) === $remoteName
            && $this->normalize($city->province?->name ?? '') === $remoteState
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalize(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), [
            'ي' => 'ی',
            'ك' => 'ک',
            '‌' => ' ',
            'ـ' => ' ',
        ]);
        $value = preg_replace('/\p{Mn}/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
