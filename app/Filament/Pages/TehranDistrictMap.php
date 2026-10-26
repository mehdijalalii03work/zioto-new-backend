<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\UserAddress;
use App\Support\Platform;
use App\Support\TehranDistrictClassifier;
use Filament\Pages\Page;
use Modules\Order\Models\Order;

class TehranDistrictMap extends Page
{
    protected static ?string $slug = 'tehran-district-map';

    protected static ?string $title = 'نقشه مناطق تهران';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'مناطق تهران';

    protected static string|\UnitEnum|null $navigationGroup = 'گزارشات مدیریتی';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.tehran-district-map';

    public string $addressSource = 'saved_addresses';

    /**
     * @var array{customers_with_tehran_address: int, confirmed_customers: int, probable_customers: int, ambiguous_customers: int, unmatched_customers: int}
     */
    public array $stats = [];

    /**
     * @var array<int, array{confirmed: int, probable: int, customers: int, color: string}>
     */
    public array $districtData = [];

    public function mount(TehranDistrictClassifier $classifier): void
    {
        $this->loadCustomerCounts($classifier);
    }

    public function updatedAddressSource(TehranDistrictClassifier $classifier): void
    {
        if (! in_array($this->addressSource, ['orders', 'saved_addresses'], true)) {
            $this->addressSource = 'saved_addresses';
        }

        $this->loadCustomerCounts($classifier);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManagementReportView->value) ?? false;
    }

    private function loadCustomerCounts(TehranDistrictClassifier $classifier): void
    {
        $this->stats = [
            'customers_with_tehran_address' => 0,
            'confirmed_customers' => 0,
            'probable_customers' => 0,
            'ambiguous_customers' => 0,
            'unmatched_customers' => 0,
        ];

        $this->districtData = [];

        foreach (range(1, 22) as $districtNumber) {
            $this->districtData[$districtNumber] = [
                'confirmed' => 0,
                'probable' => 0,
                'customers' => 0,
                'color' => '#f3e8dc',
            ];
        }

        $addressesQuery = UserAddress::withoutTenantScope()
            ->join('users', 'users.id', '=', 'user_addresses.user_id')
            ->join('cities', 'cities.id', '=', 'user_addresses.city_id')
            ->whereNull('users.deleted_at')
            ->where('cities.name', 'تهران')
            ->orderBy('user_addresses.user_id')
            ->orderByDesc('user_addresses.is_default')
            ->orderByDesc('user_addresses.updated_at')
            ->orderByDesc('user_addresses.id');

        if ($this->addressSource === 'orders') {
            $orderCustomerIds = Order::withoutTenantScope()
                ->where('platform', Platform::MAIN)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->distinct();

            $addressesQuery->whereIn('user_addresses.user_id', $orderCustomerIds);
        }

        $customers = $addressesQuery->get([
            'user_addresses.user_id',
            'user_addresses.district',
            'user_addresses.address_line',
        ])
            ->unique('user_id');

        $this->stats['customers_with_tehran_address'] = $customers->count();

        foreach ($customers as $customer) {
            $match = $classifier->classify($customer->district, $customer->address_line);
            $statusKey = match ($match['status']) {
                'confirmed' => 'confirmed_customers',
                'probable' => 'probable_customers',
                'ambiguous' => 'ambiguous_customers',
                default => 'unmatched_customers',
            };

            $this->stats[$statusKey]++;

            if ($match['district'] === null) {
                continue;
            }

            $districtKey = $match['status'] === 'confirmed' ? 'confirmed' : 'probable';
            $this->districtData[$match['district']][$districtKey]++;
            $this->districtData[$match['district']]['customers']++;
        }

        $maximumCustomers = max(1, collect($this->districtData)->max('customers'));

        foreach ($this->districtData as $districtNumber => $data) {
            $this->districtData[$districtNumber]['color'] = $this->getColorForIntensity(
                sqrt($data['customers'] / $maximumCustomers),
            );
        }
    }

    private function getColorForIntensity(float $intensity): string
    {
        $from = [243, 232, 220];
        $to = [190, 65, 35];
        $ratio = max(0.0, min(1.0, $intensity));
        $channels = array_map(
            fn (int $start, int $end): int => (int) round($start + (($end - $start) * $ratio)),
            $from,
            $to,
        );

        return sprintf('#%02x%02x%02x', ...$channels);
    }
}
