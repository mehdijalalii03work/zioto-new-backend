<?php

namespace Tests\Feature\Commands;

use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The WordPress import left 2,419 addresses with a city but no province.
 * This command repairs them by re-saving the rows so the model hook fills
 * province_id, and must be safe to run twice.
 */
class BackfillAddressProvincesTest extends TestCase
{
    use RefreshDatabase;

    private function province(int $id, string $name, string $slug): Province
    {
        return Province::create(['id' => $id, 'name' => $name, 'slug' => $slug]);
    }

    public function test_it_fills_the_province_for_addresses_that_have_a_city(): void
    {
        $province = $this->province(20, 'تهران', 'tehran-backfill');
        $city = City::create(['province_id' => $province->id, 'name' => 'تهران', 'slug' => 'tehran-city-backfill']);
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        // Simulate the legacy row: province_id is null even though the city is set.
        $address->forceFill(['city_id' => $city->id, 'province_id' => null])->saveQuietly();
        $this->assertNull($address->fresh()->province_id);

        $this->artisan('addresses:backfill-provinces')->assertExitCode(0);

        $this->assertSame($province->id, $address->fresh()->province_id);
    }

    public function test_it_leaves_addresses_without_a_city_untouched(): void
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        $this->artisan('addresses:backfill-provinces')->assertExitCode(0);

        $this->assertNull($address->fresh()->province_id);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $province = $this->province(21, 'اصفهان', 'isfahan-backfill');
        $city = City::create(['province_id' => $province->id, 'name' => 'اصفهان', 'slug' => 'isfahan-city-backfill']);
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id, 'city_id' => null]);
        $address->forceFill(['city_id' => $city->id, 'province_id' => null])->saveQuietly();

        $this->artisan('addresses:backfill-provinces --dry-run')
            ->expectsOutputToContain('آدرس بدون province_id و دارای city_id پیدا شد')
            ->assertExitCode(0);

        $this->assertNull($address->fresh()->province_id);
    }

    public function test_it_is_idempotent(): void
    {
        $province = $this->province(22, 'فارس', 'fars-backfill');
        $city = City::create(['province_id' => $province->id, 'name' => 'شیراز', 'slug' => 'shiraz-backfill']);
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id, 'city_id' => null]);
        $address->forceFill(['city_id' => $city->id, 'province_id' => null])->saveQuietly();

        $this->artisan('addresses:backfill-provinces')->assertExitCode(0);
        $this->assertSame($province->id, $address->fresh()->province_id);

        $this->artisan('addresses:backfill-provinces')
            ->expectsOutputToContain('همه آدرس‌ها province_id دارند')
            ->assertExitCode(0);

        $this->assertSame($province->id, $address->fresh()->province_id);
    }
}
