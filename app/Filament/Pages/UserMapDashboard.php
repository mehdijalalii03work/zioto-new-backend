<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserMapDashboard extends Page
{
    protected static ?string $slug = 'user-map';

    protected static ?string $title = 'نقشه کاربران';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'نقشه کاربران';

    protected static string|\UnitEnum|null $navigationGroup = 'داشبورد';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.user-map-dashboard';

    /**
     * @var array{total_users: int, mapped_users: int, unmapped_users: int, active_provinces: int, top_province: ?string, top_province_count: int, total_provinces: int}
     */
    public array $stats = [];

    /**
     * Province name, unique user count and choropleth colour, keyed by province slug.
     *
     * @var array<string, array{name: string, count: int, color: string}>
     */
    public array $provinceData = [];

    /** @var list<string> */
    public array $legendColors = [];

    public int $maxUsers = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::DashboardView->value) ?? false;
    }

    public function mount(): void
    {
        $this->loadProvinceStats();

        $this->legendColors = array_map(
            fn (int $step): string => $this->getColorForIntensity($step / 4),
            range(0, 4),
        );
    }

    private function loadProvinceStats(): void
    {
        $userCountsByProvince = $this->userCountsByProvince();

        $provinces = Province::all();

        $this->maxUsers = $userCountsByProvince->isEmpty() ? 1 : $userCountsByProvince->max();

        $activeProvinces = 0;
        $topProvince = null;
        $topCount = 0;

        foreach ($provinces as $province) {
            $count = $userCountsByProvince->get($province->id, 0);

            if ($count > 0) {
                $activeProvinces++;
            }

            if ($count > $topCount) {
                $topCount = $count;
                $topProvince = $province->name;
            }

            $this->provinceData[$province->slug] = [
                'name' => $province->name,
                'count' => $count,
                'color' => $this->getColorForIntensity(
                    $this->maxUsers > 0 ? min(1, $count / $this->maxUsers) : 0,
                ),
            ];
        }

        $usersWithAddress = $this->distinctUserCount(fn ($query) => $query);
        $unmappedUsers = $this->distinctUserCount(fn ($query) => $query
            ->whereNull('user_addresses.province_id')
            ->whereNull('cities.province_id'));

        $this->stats = [
            'total_users' => User::withoutTenantScope()->count(),
            'mapped_users' => $usersWithAddress - $unmappedUsers,
            'unmapped_users' => $unmappedUsers,
            'active_provinces' => $activeProvinces,
            'total_provinces' => $provinces->count(),
            'top_province' => $topProvince,
            'top_province_count' => $topCount,
        ];
    }

    /**
     * Distinct living users per province. The province falls back to the
     * province of the linked city, because 2,419 addresses imported from
     * WordPress only carry a city.
     *
     * @return Collection<int, int>
     */
    private function userCountsByProvince(): Collection
    {
        return $this->addressQuery()
            ->whereRaw('COALESCE(user_addresses.province_id, cities.province_id) IS NOT NULL')
            ->selectRaw('COALESCE(user_addresses.province_id, cities.province_id) AS resolved_province_id')
            ->selectRaw('COUNT(DISTINCT user_addresses.user_id) AS users')
            ->groupBy('resolved_province_id')
            ->pluck('users', 'resolved_province_id');
    }

    /**
     * @param  callable(Builder): void  $constrain
     */
    private function distinctUserCount(callable $constrain): int
    {
        $query = $this->addressQuery();

        $constrain($query);

        return $query->distinct()->count('user_addresses.user_id');
    }

    /**
     * Alive addresses of living users, joined to the city so the province can
     * be derived when the address itself has none.
     */
    private function addressQuery(): Builder
    {
        return UserAddress::withoutTenantScope()
            ->join('users', 'users.id', '=', 'user_addresses.user_id')
            ->leftJoin('cities', 'cities.id', '=', 'user_addresses.city_id')
            ->whereNull('users.deleted_at');
    }

    private function getColorForIntensity(float $intensity): string
    {
        // Light red to dark red
        $r = 255;
        $g = (int) (200 * (1 - $intensity));
        $b = (int) (200 * (1 - $intensity));

        return sprintf('rgb(%d, %d, %d)', $r, $g, $b);
    }
}
