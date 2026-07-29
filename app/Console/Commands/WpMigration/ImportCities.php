<?php

namespace App\Console\Commands\WpMigration;

use App\Models\City;
use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportCities extends Command
{
    protected $signature = 'migrate:wp-cities
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import Iranian cities from sajaddp/list-of-cities-in-Iran into Laravel';

    private const PROVINCES_URL = 'https://raw.githubusercontent.com/sajaddp/list-of-cities-in-Iran/refs/heads/main/dist/json/provinces.json';

    private const CITIES_URL = 'https://raw.githubusercontent.com/sajaddp/list-of-cities-in-Iran/refs/heads/main/dist/json/cities-filtered.json';

    public function handle(): int
    {
        $this->line('Downloading provinces...');
        $repoProvinces = $this->fetchJson(self::PROVINCES_URL);
        if ($repoProvinces === null) {
            $this->error('Failed to download provinces.json');

            return Command::FAILURE;
        }

        $this->line('Downloading cities...');
        $repoCities = $this->fetchJson(self::CITIES_URL);
        if ($repoCities === null) {
            $this->error('Failed to download cities-filtered.json');

            return Command::FAILURE;
        }

        $this->line('Building province name mapping...');
        $provinceMap = $this->buildProvinceMap($repoProvinces);
        if ($provinceMap === null) {
            $this->error('Could not match provinces between repo and Laravel');

            return Command::FAILURE;
        }

        $laravelProvinceIds = Province::pluck('id', 'name')->toArray();

        $existing = $this->getExistingCityKeys();

        $imported = 0;
        $skipped = 0;
        $skippedNumbered = 0;
        $noMatch = 0;

        foreach ($repoCities as $city) {
            $repoProvinceId = $city['province_id'];
            $provinceName = $provinceMap[$repoProvinceId] ?? null;

            if ($provinceName === null || ! isset($laravelProvinceIds[$provinceName])) {
                $noMatch++;

                continue;
            }

            $laravelProvinceId = $laravelProvinceIds[$provinceName];
            $cityName = $this->normalize(trim($city['name']));

            if (empty($cityName)) {
                $skipped++;

                continue;
            }

            if ($this->isNumberedEntry($cityName)) {
                $skippedNumbered++;

                continue;
            }

            $key = $laravelProvinceId.'|'.$cityName;
            if (isset($existing[$key])) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $imported++;

                continue;
            }

            $slug = Str::slug($cityName);
            if (empty($slug)) {
                $slug = 'city-'.$laravelProvinceId.'-'.Str::slug($cityName.' '.$city['id']);
                if (empty($slug)) {
                    $slug = 'city-'.$city['id'];
                }
            }

            $cityModel = new City;
            $cityModel->timestamps = false;
            $cityModel->forceFill([
                'province_id' => $laravelProvinceId,
                'name' => $cityName,
                'slug' => $slug,
            ]);
            $cityModel->save();

            $imported++;
        }

        $this->newLine();
        $this->info('Repo provinces: '.count($repoProvinces));
        $this->info('Repo cities: '.count($repoCities));
        $this->info('Current Laravel cities: '.count($existing));
        $this->info("Imported: $imported");
        $this->info("Already exists: $skipped");
        $this->info("Skipped (numbered zones): $skippedNumbered");
        $this->info("No province match: $noMatch");

        return Command::SUCCESS;
    }

    protected function fetchJson(string $url): ?array
    {
        try {
            $response = Http::timeout(30)->get($url);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            $this->error('HTTP error: '.$e->getMessage());

            return null;
        }
    }

    protected function buildProvinceMap(array $repoProvinces): ?array
    {
        $provinceNames = Province::pluck('id', 'name')->toArray();

        $map = [];
        foreach ($repoProvinces as $province) {
            $repoName = $province['name'];
            if (isset($provinceNames[$repoName])) {
                $map[$province['id']] = $repoName;
            }
        }

        if (count($map) < 30) {
            $this->warn('Only matched '.count($map).' provinces out of '.count($repoProvinces));

            return null;
        }

        return $map;
    }

    protected function getExistingCityKeys(): array
    {
        return City::all()
            ->mapWithKeys(fn ($city) => [$city->province_id.'|'.$this->normalize($city->name) => true])
            ->toArray();
    }

    protected function normalize(string $name): string
    {
        $name = str_replace("\u{200C}", '', $name);
        $name = str_replace("\u{064A}", "\u{06CC}", $name);
        $name = str_replace("\u{0643}", "\u{06A9}", $name);

        return trim($name);
    }

    protected function isNumberedEntry(string $name): bool
    {
        return (bool) preg_match('/\d+$/', $name);
    }
}
