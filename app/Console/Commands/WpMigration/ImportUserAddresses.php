<?php

namespace App\Console\Commands\WpMigration;

use App\Models\City;
use App\Models\Province;
use App\Models\UserAddress;
use App\Services\WpMigration\WpDatabase;
use Illuminate\Console\Command;

class ImportUserAddresses extends Command
{
    protected $signature = 'migrate:wp-user-addresses
                            {--dry-run : Only show stats without inserting}';

    protected $description = 'Import WordPress user billing/shipping addresses into Laravel user_addresses';

    protected static array $provinceCache = [];

    protected static array $cityCache = [];

    public function handle(WpDatabase $wp): int
    {
        if (! $wp->table('user_mapping')->exists()) {
            $this->error('User mapping table not found. Run migrate:wp-users first.');

            return Command::FAILURE;
        }

        $mappedUsers = $wp->table('user_mapping')->count();
        $this->line("Mapped users: $mappedUsers");

        $imported = 0;
        $skipped = 0;

        $wp->table('user_mapping')
            ->orderBy('wp_user_id')
            ->chunk(100, function ($mappings) use ($wp, &$imported, &$skipped) {
                foreach ($mappings as $map) {
                    $laravelUserId = $map->laravel_user_id;

                    if (UserAddress::where('user_id', $laravelUserId)->exists()) {
                        $skipped++;

                        continue;
                    }

                    $billingAddress = $this->buildAddress($wp, $map->wp_user_id, 'billing');

                    if (! $billingAddress) {
                        $skipped++;

                        continue;
                    }

                    if (! $this->option('dry-run')) {
                        $billingAddress['user_id'] = $laravelUserId;
                        $billingAddress['is_default'] = true;
                        $billingAddress['is_billing'] = true;

                        $address = new UserAddress;
                        $address->timestamps = false;
                        $address->forceFill($billingAddress);
                        $address->save();
                    }

                    $imported++;

                    $shippingAddress = $this->buildAddress($wp, $map->wp_user_id, 'shipping');

                    if ($shippingAddress && ! $this->option('dry-run')) {
                        $shippingAddress['user_id'] = $laravelUserId;
                        $shippingAddress['is_default'] = false;

                        $address = new UserAddress;
                        $address->timestamps = false;
                        $address->forceFill($shippingAddress);
                        $address->save();
                        $imported++;
                    }
                }
            });

        $this->newLine();
        $this->info("Import complete: $imported addresses created, $skipped skipped (already have addresses)");

        return Command::SUCCESS;
    }

    protected function buildAddress(WpDatabase $wp, int $wpUserId, string $type = 'billing'): ?array
    {
        $firstName = $wp->getMeta($wpUserId, $type.'_first_name', 'usermeta');
        $lastName = $wp->getMeta($wpUserId, $type.'_last_name', 'usermeta');
        $address1 = $wp->getMeta($wpUserId, $type.'_address_1', 'usermeta');
        $address2 = $wp->getMeta($wpUserId, $type.'_address_2', 'usermeta');
        $cityName = $wp->getMeta($wpUserId, $type.'_city', 'usermeta');
        $stateName = $wp->getMeta($wpUserId, $type.'_state', 'usermeta');
        $postcode = $wp->getMeta($wpUserId, $type.'_postcode', 'usermeta');
        $phone = $wp->getMeta($wpUserId, $type.'_phone', 'usermeta');

        if ($type === 'billing' && empty($phone)) {
            $phone = $wp->getMeta($wpUserId, 'billing_phone', 'usermeta');
        }

        if (empty($address1) && empty($cityName)) {
            return null;
        }

        $provinceId = $this->resolveProvinceId($stateName);
        $cityId = $this->resolveCityId($cityName, $provinceId);

        $receiverName = trim(($firstName ?? '').' '.($lastName ?? ''));
        if (empty($receiverName)) {
            $receiverName = $wp->getMeta($wpUserId, 'first_name', 'usermeta').' '.$wp->getMeta($wpUserId, 'last_name', 'usermeta');
        }

        return [
            'label' => $type === 'billing' ? 'آدرس' : 'آدرس ارسال',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district' => null,
            'postal_code' => $postcode ?: null,
            'address_line' => trim(($address1 ?? '').' '.($address2 ?? '')),
            'receiver_name' => trim($receiverName) ?: null,
            'receiver_phone' => $phone ?: null,
            'is_default' => false,
            'is_billing' => $type === 'billing',
        ];
    }

    protected function resolveProvinceId(?string $stateName): ?int
    {
        if (empty($stateName)) {
            return null;
        }

        $stateName = trim($stateName);

        if (isset(static::$provinceCache[$stateName])) {
            return static::$provinceCache[$stateName];
        }

        $province = Province::where('slug', $stateName)
            ->orWhere('name', $stateName)
            ->first();

        $id = $province?->id;
        static::$provinceCache[$stateName] = $id;

        return $id;
    }

    protected function resolveCityId(?string $cityName, ?int $provinceId): ?int
    {
        if (empty($cityName)) {
            return null;
        }

        $cityName = trim($cityName);
        $cacheKey = $cityName.'|'.($provinceId ?? 'null');

        if (isset(static::$cityCache[$cacheKey])) {
            return static::$cityCache[$cacheKey];
        }

        $query = City::where(function ($q) use ($cityName) {
            $q->where('slug', $cityName)
                ->orWhere('name', $cityName);
        });

        if ($provinceId) {
            $query->where('province_id', $provinceId);
        }

        $city = $query->first();

        static::$cityCache[$cacheKey] = $city?->id;

        return $city?->id;
    }
}
