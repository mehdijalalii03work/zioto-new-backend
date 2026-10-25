<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\TehranDistrictMap;
use App\Models\City;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\Platform;
use App\Support\TehranDistrictClassifier;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Tests\TestCase;

class TehranDistrictMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_it_renders_all_22_tehran_district_shapes_and_labels(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);

        $html = Livewire::actingAs($admin, 'web')
            ->test(TehranDistrictMap::class)
            ->assertSuccessful()
            ->assertSee('نقشه مناطق ۲۲گانه تهران')
            ->assertSee('OpenStreetMap contributors')
            ->html();

        $this->assertSame(22, substr_count($html, 'data-tehran-district-number='));
        $this->assertSame(22, substr_count($html, 'class="tehran-district-label"'));
    }

    public function test_it_uses_fuller_neighborhood_mapping_and_keeps_conflicting_names_ambiguous(): void
    {
        $classifier = app(TehranDistrictClassifier::class);

        $this->assertSame(
            ['status' => 'probable', 'district' => 20],
            $classifier->classify('دولت‌آباد', null),
        );
        $this->assertSame(
            ['status' => 'ambiguous', 'district' => null],
            $classifier->classify('صادقیه', null),
        );
        $this->assertSame(
            ['status' => 'ambiguous', 'district' => null],
            $classifier->classify('تهرانپارس', null),
        );
    }

    public function test_it_maps_order_customers_by_explicit_region_and_neighborhood_evidence(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $tehran = City::factory()->create(['name' => 'تهران']);
        $qom = City::factory()->create(['name' => 'قم']);

        $confirmedCustomer = User::factory()->create();
        Order::factory()->for($confirmedCustomer)->create();
        UserAddress::factory()->for($confirmedCustomer)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'منطقه ۲۲ شهرک گلستان',
            'is_default' => true,
        ]);

        $probableCustomer = User::factory()->create();
        Order::factory()->for($probableCustomer)->create();
        UserAddress::factory()->for($probableCustomer)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'سعادت آباد',
            'is_default' => true,
        ]);

        $ambiguousCustomer = User::factory()->create();
        Order::factory()->for($ambiguousCustomer)->create();
        UserAddress::factory()->for($ambiguousCustomer)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'نیاوران و تهرانپارس',
            'is_default' => true,
        ]);

        $conflictingCustomer = User::factory()->create();
        Order::factory()->for($conflictingCustomer)->create();
        UserAddress::factory()->for($conflictingCustomer)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'منطقه ۲۲',
            'address_line' => 'نیاوران',
            'is_default' => true,
        ]);

        $unmatchedCustomer = User::factory()->create();
        Order::factory()->for($unmatchedCustomer)->create();
        UserAddress::factory()->for($unmatchedCustomer)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'یک نشانی بدون نام محله',
            'is_default' => true,
        ]);

        $customerWithoutOrders = User::factory()->create();
        UserAddress::factory()->for($customerWithoutOrders)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'منطقه ۳',
            'is_default' => true,
        ]);

        $customerOutsideTehran = User::factory()->create();
        Order::factory()->for($customerOutsideTehran)->create();
        UserAddress::factory()->for($customerOutsideTehran)->create([
            'city_id' => $qom->id,
            'district' => 'منطقه ۱',
            'is_default' => true,
        ]);

        $tapsiCustomer = User::factory()->create();
        Order::factory()->for($tapsiCustomer)->create(['platform' => Platform::TAPSI]);
        UserAddress::factory()->for($tapsiCustomer)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'منطقه ۱',
            'is_default' => true,
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(TehranDistrictMap::class)
            ->set('addressSource', 'orders')
            ->assertSet('stats.confirmed_customers', 1)
            ->assertSet('stats.probable_customers', 1)
            ->assertSet('stats.ambiguous_customers', 2)
            ->assertSet('stats.unmatched_customers', 1)
            ->assertSet('districtData.22.confirmed', 1)
            ->assertSet('districtData.2.probable', 1)
            ->assertSee('تعداد مشتریان دارای تطبیق یکتاست');
    }

    public function test_it_can_map_saved_tehran_addresses_for_customers_without_orders(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $tehran = City::factory()->create(['name' => 'تهران']);

        $customerWithOrder = User::factory()->create();
        Order::factory()->for($customerWithOrder)->create();
        UserAddress::factory()->for($customerWithOrder)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'منطقه ۲۲',
            'is_default' => true,
        ]);

        $customerWithoutOrder = User::factory()->create();
        UserAddress::factory()->for($customerWithoutOrder)->create([
            'city_id' => $tehran->id,
            'province_id' => null,
            'district' => 'منطقه ۳',
            'is_default' => true,
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(TehranDistrictMap::class)
            ->assertSet('stats.customers_with_tehran_address', 2)
            ->assertSet('stats.confirmed_customers', 2)
            ->assertSet('districtData.22.confirmed', 1)
            ->assertSet('districtData.3.confirmed', 1)
            ->set('addressSource', 'orders')
            ->assertSet('stats.customers_with_tehran_address', 1);
    }
}
