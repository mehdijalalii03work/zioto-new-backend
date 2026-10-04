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
     * @var array<string, array{name: string, count: int, color: string, dark_fill: bool}>
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

            $scale = $this->getColorScale($count);

            $this->provinceData[$province->slug] = [
                'name' => $province->name,
                'count' => $count,
                'color' => $this->getColorForIntensity($scale),
                'dark_fill' => $scale > 0.5,
            ];
        }

        $mappedUsers = $this->distinctUserCount(fn ($query) => $query
            ->where(fn ($address) => $address
                ->whereNotNull('user_addresses.province_id')
                ->orWhereNotNull('cities.province_id')));

        $unmappedUsers = $this->distinctUserCount(fn ($query) => $query
            ->whereNull('user_addresses.province_id')
            ->whereNull('cities.province_id'));

        $this->stats = [
            'total_users' => User::withoutTenantScope()->count(),
            'mapped_users' => $mappedUsers,
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

    /**
     * Share of the busiest province on a square-root scale. Tehran holds ten
     * times the next province, so a linear scale flattens every other province
     * into one indistinguishable pale tone.
     */
    private function getColorScale(int $count): float
    {
        if ($this->maxUsers < 1) {
            return 0.0;
        }

        return min(1.0, sqrt($count / $this->maxUsers));
    }

    /**
     * Sequential red ramp: light brick, red, near-black red.
     *
     * @var array<int, array{int, int, int}>
     */
    private const array COLOR_RAMP = [
        [240, 190, 180],
        [198, 40, 40],
        [92, 0, 0],
    ];

    private function getColorForIntensity(float $intensity): string
    {
        $intensity = max(0.0, min(1.0, $intensity));

        [$from, $to] = $intensity <= 0.5
            ? [self::COLOR_RAMP[0], self::COLOR_RAMP[1]]
            : [self::COLOR_RAMP[1], self::COLOR_RAMP[2]];

        $ratio = $intensity <= 0.5 ? $intensity * 2 : ($intensity - 0.5) * 2;

        [$r, $g, $b] = array_map(
            fn (int $start, int $end): int => (int) round($start + ($end - $start) * $ratio),
            $from,
            $to,
        );

        return sprintf('rgb(%d, %d, %d)', $r, $g, $b);
    }
}
