<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class UserMapDashboard extends Page
{
    protected static ?string $slug = 'user-map';

    protected static ?string $title = 'نقشه کاربران';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'نقشه کاربران';

    protected static string|\UnitEnum|null $navigationGroup = 'داشبورد';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.user-map-dashboard';

    public array $stats = [];

    public array $provinceData = [];

    public int $maxUsers = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::DashboardView->value) ?? false;
    }

    public function mount(): void
    {
        $this->loadStats();
        $this->loadProvinceData();
    }

    private function loadStats(): void
    {
        $totalUsers = User::withoutTenantScope()->count();

        $addressesWithProvince = UserAddress::withoutTenantScope()
            ->whereNotNull('province_id')
            ->with('province:id,name')
            ->get();

        $provinceUserCounts = $addressesWithProvince
            ->groupBy('province_id')
            ->map(fn (Collection $addresses) => $addresses->unique('user_id')->count())
            ->toArray();

        $activeProvinces = count($provinceUserCounts);
        $totalProvinces = Province::count();

        $topProvince = null;
        $topCount = 0;

        foreach ($provinceUserCounts as $provinceId => $count) {
            if ($count > $topCount) {
                $topCount = $count;
                $province = Province::find($provinceId);
                $topProvince = $province?->name ?? '—';
            }
        }

        $this->maxUsers = max($provinceUserCounts) ?: 1;

        $this->stats = [
            'total_users' => $totalUsers,
            'active_provinces' => $activeProvinces,
            'top_province' => $topProvince,
            'top_province_count' => $topCount,
            'total_provinces' => $totalProvinces,
        ];
    }

    private function loadProvinceData(): void
    {
        $addressesWithProvince = UserAddress::withoutTenantScope()
            ->whereNotNull('province_id')
            ->with('province:id,name')
            ->get();

        $provinceUserCounts = $addressesWithProvince
            ->groupBy('province_id')
            ->map(fn (Collection $addresses) => $addresses->unique('user_id')->count())
            ->toArray();

        $provinces = Province::all()->keyBy('id');

        foreach ($provinces as $id => $province) {
            $count = $provinceUserCounts[$id] ?? 0;
            $intensity = $this->maxUsers > 0 ? min(1, $count / $this->maxUsers) : 0;

            $this->provinceData[$id] = [
                'name' => $province->name,
                'count' => $count,
                'intensity' => $intensity,
                'color' => $this->getColorForIntensity($intensity),
            ];
        }
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
