<?php

namespace App\Support;

class TehranDistrictClassifier
{
    /**
     * @var array<string, list<int>>|null
     */
    private ?array $neighborhoodDistrictMap = null;

    /**
     * @return array{status: 'confirmed'|'probable'|'ambiguous'|'unmatched', district: ?int}
     */
    public function classify(?string $district, ?string $addressLine): array
    {
        $address = $this->normalize(implode(' ', array_filter([$district, $addressLine])));
        preg_match_all('/منطقه\s*(?:شماره\s*)?([0-9۰-۹٠-٩]{1,2})/u', $address, $explicitMatches);
        $neighborhoodDistricts = $this->findNeighborhoodDistricts($address);

        $explicitDistricts = collect($explicitMatches[1] ?? [])
            ->map(fn (string $number): int => $this->toInteger($number))
            ->filter(fn (int $number): bool => $number >= 1 && $number <= 22)
            ->unique()
            ->values();

        if ($explicitDistricts->count() === 1) {
            $explicitDistrict = $explicitDistricts->first();

            if ($neighborhoodDistricts !== [] && ! isset($neighborhoodDistricts[$explicitDistrict])) {
                return ['status' => 'ambiguous', 'district' => null];
            }

            return ['status' => 'confirmed', 'district' => $explicitDistricts->first()];
        }

        if ($explicitDistricts->count() > 1) {
            return ['status' => 'ambiguous', 'district' => null];
        }

        if (count($neighborhoodDistricts) === 1) {
            return ['status' => 'probable', 'district' => (int) array_key_first($neighborhoodDistricts)];
        }

        if (count($neighborhoodDistricts) > 1) {
            return ['status' => 'ambiguous', 'district' => null];
        }

        return ['status' => 'unmatched', 'district' => null];
    }

    /** @return array<int, true> */
    private function findNeighborhoodDistricts(string $address): array
    {
        $matches = [];

        foreach ($this->getNeighborhoodDistrictMap() as $neighborhood => $districtNumbers) {
            $pattern = '/(?<!\p{L})'.preg_quote($neighborhood, '/').'(?!\p{L})/u';

            if (preg_match($pattern, $address) === 1) {
                foreach ($districtNumbers as $districtNumber) {
                    $matches[$districtNumber] = true;
                }
            }
        }

        return $matches;
    }

    /**
     * @return array<string, list<int>>
     */
    private function getNeighborhoodDistrictMap(): array
    {
        if ($this->neighborhoodDistrictMap !== null) {
            return $this->neighborhoodDistrictMap;
        }

        $source = json_decode(
            file_get_contents(resource_path('data/tehran-neighborhood-districts.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $neighborhoods = [];

        foreach ($source['neighborhoods'] as $neighborhood) {
            $normalizedName = $this->normalize($neighborhood['name']);
            $neighborhoods[$normalizedName][] = (int) $neighborhood['district'];
        }

        foreach ($source['aliases'] as $alias) {
            $normalizedName = $this->normalize($alias['name']);
            $neighborhoods[$normalizedName] = array_merge(
                $neighborhoods[$normalizedName] ?? [],
                $alias['districts'],
            );
        }

        foreach ($neighborhoods as $name => $districtNumbers) {
            $neighborhoods[$name] = array_values(array_unique(array_filter(
                $districtNumbers,
                fn (int $districtNumber): bool => $districtNumber >= 1 && $districtNumber <= 22,
            )));
        }

        return $this->neighborhoodDistrictMap = $neighborhoods;
    }

    private function normalize(string $value): string
    {
        $value = strtr(mb_strtolower($value), [
            'ي' => 'ی',
            'ك' => 'ک',
            '‌' => ' ',
            'ـ' => ' ',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);

        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }

    private function toInteger(string $number): int
    {
        return (int) $this->normalize($number);
    }
}
