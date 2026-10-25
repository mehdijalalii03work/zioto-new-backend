# User Map Province Stats Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/admin/user-map` report truthful user-distribution numbers by deriving `province_id` from the address' city wherever it is missing, and by disclosing data coverage in the stat cards.

**Architecture:** A single model invariant on `UserAddress` fills `province_id` from `cities.province_id` on save, so every write path (API, Filament, WP importer) keeps the column populated going forward. A one-off artisan command replays that hook over the 2,419 legacy rows. The dashboard then counts users per province in a single grouped SQL query (instead of loading every address into memory) and derives "active provinces" from the province list it actually renders, so the card can never disagree with the map.

**Tech Stack:** Laravel 13, Eloquent, Livewire 4, Filament 5, PHPUnit 12, MySQL (SQLite in tests).

## Global Constraints

- Locale for all user-facing strings and command output: **Persian (Farsi)**.
- Artisan commands use PHP attributes `#[Signature('namespace:verb']` and `#[Description('...')]`, return `self::SUCCESS`, and mirror `app/Console/Commands/FillOrderShippingTaxes.php`.
- Tests are PHPUnit classes (never Pest). DB-backed tests live in `tests/Feature/**` and use `Illuminate\Foundation\Testing\RefreshDatabase`.
- Run `vendor/bin/pint --dirty --format agent` after touching any PHP file.
- Run the narrowest test first: `php artisan test --compact tests/Feature/<path>`.
- Never write documentation files unless asked; `USER-MAP-STATS-AUDIT.md` already exists and needs no edits.
- Do not change `composer.json` / `package.json` dependencies.
- The map SVG partial (`resources/views/filament/pages/partials/iran-provinces.blade.php`) binds to `$provinceData` **keyed by `provinces.slug`** — never re-key it by id.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `app/Models/UserAddress.php` | Owns the invariant: a missing `province_id` is derived from the linked city on save. |
| `app/Console/Commands/BackfillAddressProvinces.php` | One-off data repair that replays the invariant over legacy rows; supports `--dry-run`. |
| `app/Filament/Pages/UserMapDashboard.php` | Builds `$stats` and `$provinceData` with one grouped query; exposes coverage numbers. |
| `resources/views/filament/pages/user-map-dashboard.blade.php` | Renders the four stat cards and the coverage note. |
| `tests/Feature/Models/UserAddressProvinceTest.php` | Proves the invariant for the model write path. |
| `tests/Feature/Commands/BackfillAddressProvincesTest.php` | Proves the backfill fills rows, is idempotent, and reports counts. |
| `tests/Feature/Admin/UserMapDashboardTest.php` | Proves the dashboard counts derived provinces and never over-reports coverage. |

---

### Task 1: Derive `province_id` from the city on save

Addresses imported from WordPress have `city_id` set but `province_id` null, because the importer resolves the province from the WP `state` meta and falls back to nothing. Every write path goes through Eloquent, so one `saving` hook repairs all of them (including future importer runs, which use `forceFill()` + `save()`).

**Files:**
- Modify: `app/Models/UserAddress.php` (add `booted()` after the `casts()` method, line 40)
- Create: `tests/Feature/Models/UserAddressProvinceTest.php`

**Interfaces:**
- Consumes: `App\Models\City` (`province_id` column), `App\Models\Province`.
- Produces: `UserAddress::booted(): void` — sets `$address->province_id` from `$address->city?->province_id` when `$address->province_id` is null and `$address->city_id` is set. No new public methods, so no later task depends on a new signature.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Models/UserAddressProvinceTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user's province is derivable from their city, so UserAddress fills a
 * missing province_id from cities.province_id instead of storing a hole.
 */
class UserAddressProvinceTest extends TestCase
{
    use RefreshDatabase;

    private function province(string $name = 'تهران'): Province
    {
        return Province::create(['id' => 10, 'name' => $name, 'slug' => 'test-province']);
    }

    private function city(Province $province, string $name = 'تهران'): City
    {
        return City::create(['province_id' => $province->id, 'name' => $name, 'slug' => 'test-city']);
    }

    public function test_it_derives_the_province_from_the_city_when_missing(): void
    {
        $province = $this->province();
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => $this->city($province)->id,
        ]);

        $this->assertSame($province->id, $address->fresh()->province_id);
    }

    public function test_it_never_overwrites_an_explicit_province(): void
    {
        $tehran = $this->province('تهران');
        $alborz = Province::create(['id' => 11, 'name' => 'البرز', 'slug' => 'test-alborz']);
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => $alborz->id,
            'city_id' => $this->city($tehran, 'تهران')->id,
        ]);

        $this->assertSame($alborz->id, $address->fresh()->province_id);
    }

    public function test_it_leaves_the_province_null_without_a_city(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        $this->assertNull($address->fresh()->province_id);
    }

    public function test_it_derives_the_province_when_an_existing_row_is_saved(): void
    {
        $province = $this->province();
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        $this->assertNull($address->fresh()->province_id);

        // Re-saving the row (what the backfill command does) fills the province.
        $address->city_id = $this->city($province)->id;
        $address->save();

        $this->assertSame($province->id, $address->fresh()->province_id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Models/UserAddressProvinceTest.php`
Expected: FAIL — `test_it_derives_the_province_from_the_city_when_missing` and `test_it_derives_the_province_when_an_existing_row_is_saved` fail because `province_id` stays null.

- [ ] **Step 3: Write the minimal implementation**

In `app/Models/UserAddress.php`, add this method directly after `casts()`:

```php
    protected static function booted(): void
    {
        // Legacy addresses (WordPress import) carry a city but no province. Derive
        // it on every write so province_id is never a hole for reporting or for
        // province-scoped shipping rates.
        static::saving(function (self $address): void {
            if ($address->province_id !== null || $address->city_id === null) {
                return;
            }

            $address->province_id = $address->city?->province_id;
        });
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Models/UserAddressProvinceTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"pint":"passed"}`

- [ ] **Step 6: Commit**

```bash
git add app/Models/UserAddress.php tests/Feature/Models/UserAddressProvinceTest.php
git commit -m "fix: derive UserAddress province_id from the linked city on save"
```

---

### Task 2: Backfill the legacy addresses

2,419 rows already exist with a city and no province. Re-saving each one replays the Task 1 hook. The command is idempotent and safe to run in production.

**Files:**
- Create: `app/Console/Commands/BackfillAddressProvinces.php`
- Create: `tests/Feature/Commands/BackfillAddressProvincesTest.php`

**Interfaces:**
- Consumes: the `UserAddress` saving hook from Task 1; `App\Models\UserAddress`, `App\Models\City`.
- Produces: artisan command `addresses:backfill-provinces` with an optional `--dry-run` flag. No PHP signatures consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Commands/BackfillAddressProvincesTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Commands/BackfillAddressProvincesTest.php`
Expected: FAIL — `Command "addresses:backfill-provinces" is not defined.`

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/BackfillAddressProvinces.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\UserAddress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('addresses:backfill-provinces {--dry-run : فقط گزارش بده و چیزی ننویس}')]
#[Description('پر کردن province_id آدرس‌های قدیمی از روی شهرشان (ایمپورت وردپرس)')]
class BackfillAddressProvinces extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $pending = UserAddress::withoutTenantScope()
            ->whereNull('province_id')
            ->whereNotNull('city_id')
            ->count();

        if ($pending === 0) {
            $this->info('همه آدرس‌ها province_id دارند.');

            return self::SUCCESS;
        }

        $this->info("{$pending} آدرس بدون province_id و دارای city_id پیدا شد.");

        if ($dryRun) {
            $this->line('حالت --dry-run: هیچ تغییری ذخیره نشد.');

            return self::SUCCESS;
        }

        $updated = 0;

        UserAddress::withoutTenantScope()
            ->whereNull('province_id')
            ->whereNotNull('city_id')
            ->with('city:id,province_id')
            ->chunkById(200, function ($addresses) use (&$updated): void {
                foreach ($addresses as $address) {
                    $address->save();

                    if ($address->province_id !== null) {
                        $updated++;
                    }
                }
            });

        $this->info("بروزرسانی کامل شد. {$updated} آدرس province_id گرفتند.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Commands/BackfillAddressProvincesTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 5: Run the command against the real database — ask the user first**

This step mutates rows in `zioto_stage` (2,419 `UPDATE`s). Get explicit confirmation before running it, and tell the user which database `.env` points at.

Run: `php artisan addresses:backfill-provinces --dry-run`
Expected: reports `2419 آدرس بدون province_id و دارای city_id پیدا شد.` and writes nothing.

Then: `php artisan addresses:backfill-provinces`
Expected: `بروزرسانی کامل شد. 2419 آدرس province_id گرفتند.`

Re-run `php artisan addresses:backfill-provinces --dry-run`
Expected: `همه آدرس‌ها province_id دارند.` — proves idempotency on real data.

- [ ] **Step 6: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"pint":"passed"}`

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/BackfillAddressProvinces.php tests/Feature/Commands/BackfillAddressProvincesTest.php
git commit -m "feat: add addresses:backfill-provinces to repair legacy address rows"
```

---

### Task 3: Count users per province in one query

`UserMapDashboard` currently loads every address into memory twice (`loadStats()` and `loadProvinceData()` each call `->get()`), groups in PHP, and derives "active provinces" from raw `province_id` group keys — which can disagree with the provinces the map actually draws. Replace both with a single grouped query plus two cheap distinct counts.

**Files:**
- Modify: `app/Filament/Pages/UserMapDashboard.php:61-123` (`loadStats()`, `loadProvinceData()`, and the `$stats` docblock at line 28)
- Test: `tests/Feature/Admin/UserMapDashboardTest.php`

**Interfaces:**
- Consumes: `App\Models\Province`, `App\Models\User`, `App\Models\UserAddress`.
- Produces: `$stats` with keys `total_users` (int), `mapped_users` (int), `unmapped_users` (int), `active_provinces` (int), `total_provinces` (int), `top_province` (?string), `top_province_count` (int). Task 4 renders exactly these keys; `mapped_users`, `unmapped_users` are new.

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/Feature/Admin/UserMapDashboardTest.php`:

```php
    public function test_it_counts_an_address_that_only_knows_its_city(): void
    {
        $province = $this->province('fars');
        $city = City::create([
            'province_id' => $province->id,
            'name' => 'شیراز',
            'slug' => 'shiraz-legacy',
        ]);

        $address = UserAddress::factory()->create([
            'user_id' => User::factory()->create()->id,
            'province_id' => null,
            'city_id' => null,
        ]);
        $address->forceFill(['province_id' => null, 'city_id' => $city->id])->saveQuietly();

        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->html();

        $shapes = $this->mapShapes($html);

        $this->assertSame(1, $shapes['fars']['count']);
        $this->assertSame('fill: rgb(255, 0, 0)', $shapes['fars']['fill']);
    }

    public function test_it_counts_an_address_without_a_city_as_unmapped(): void
    {
        UserAddress::factory()->create([
            'user_id' => User::factory()->create()->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        $stats = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->instance()
            ->stats;

        $this->assertSame(0, $stats['mapped_users']);
        $this->assertSame(1, $stats['unmapped_users']);
        $this->assertSame(0, $stats['active_provinces']);
    }

    public function test_active_provinces_never_exceeds_the_rendered_map(): void
    {
        $province = $this->province('tehran');

        UserAddress::factory()->count(2)->create([
            'user_id' => User::factory()->create()->id,
            'province_id' => $province->id,
            'city_id' => null,
        ]);

        $component = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful();

        $stats = $component->instance()->stats;

        $rendered = count(array_filter(
            $this->mapShapes($component->html()),
            fn (array $shape): bool => $shape['count'] > 0,
        ));

        $this->assertSame(1, $rendered);
        $this->assertSame($rendered, $stats['active_provinces']);
        $this->assertLessThanOrEqual(count(Province::all()), $stats['active_provinces']);
    }
```

Add the `use App\Models\City;` import to the test file if it is not already present.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/UserMapDashboardTest.php`
Expected: FAIL — `array_key_exists` style failure on `$stats['mapped_users']` / `$stats['unmapped_users']` (undefined array key), and `test_it_counts_an_address_that_only_knows_its_city` fails because the city-only address is not counted.

- [ ] **Step 3: Rewrite the two loaders**

In `app/Filament/Pages/UserMapDashboard.php`, replace the `$stats` docblock with:

```php
    /**
     * @var array{total_users: int, mapped_users: int, unmapped_users: int, active_provinces: int, top_province: ?string, top_province_count: int, total_provinces: int}
     */
    public array $stats = [];
```

Replace `loadStats()` and `loadProvinceData()` with a single loader, and have `mount()` call only that one:

```php
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
```

Fix the imports of `app/Filament/Pages/UserMapDashboard.php`:

```php
use Illuminate\Database\Eloquent\Builder;   // replaces Illuminate\Database\Eloquent\Collection
use Illuminate\Support\Collection;
```

`Illuminate\Database\Eloquent\Collection` is no longer used — the old `map(fn (Collection $addresses) => ...)` grouping is gone. Keep `App\Models\User`, `App\Models\UserAddress`, `App\Models\Province` — all three are still referenced.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/UserMapDashboardTest.php`
Expected: PASS — 7 tests.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"pint":"passed"}`

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/UserMapDashboard.php tests/Feature/Admin/UserMapDashboardTest.php
git commit -m "fix: count dashboard users per province in one query and report coverage"
```

---

### Task 4: Make the stat cards state their coverage

The headline card says "total users" (4,715) next to a map that only knows the users whose address has a province, and "active provinces" (22) hides that 31 provinces exist. Four cards, each self-describing, plus one conditional note under the map.

**Files:**
- Modify: `resources/views/filament/pages/user-map-dashboard.blade.php:7-60` (the four stat cards) and line 141-151 (legend block, to append the note after it)

**Interfaces:**
- Consumes: `$stats` keys produced by Task 3 — `total_users`, `mapped_users`, `unmapped_users`, `active_provinces`, `total_provinces`, `top_province`, `top_province_count`.
- Produces: no new PHP interface; rendered copy only.

- [ ] **Step 1: Replace the four stat cards**

Replace the whole `{{-- Stats Cards --}}` block (from the `{{-- Stats Cards --}}` comment through the closing `</div>` of that grid) with:

```blade
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Users --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-primary-400 to-primary-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تعداد کل کاربران</p>
                    <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ number_format($stats['total_users']) }}</p>
                </div>
                <div class="rounded-2xl bg-primary-50 p-3 dark:bg-primary-500/10">
                    <x-heroicon-o-users class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </div>

        {{-- Users Placed On The Map --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-emerald-400 to-emerald-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">کاربران روی نقشه</p>
                    <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ number_format($stats['mapped_users']) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">از {{ number_format($stats['total_users']) }} کاربر</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                    <x-heroicon-o-map-pin class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
        </div>

        {{-- Covered Provinces --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-purple-400 to-purple-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">استان‌های دارای کاربر</p>
                    <p class="text-3xl font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ number_format($stats['active_provinces']) }}
                        <span class="text-lg font-medium text-gray-400 dark:text-gray-500">از {{ number_format($stats['total_provinces']) }}</span>
                    </p>
                </div>
                <div class="rounded-2xl bg-purple-50 p-3 dark:bg-purple-500/10">
                    <x-heroicon-o-squares-2x2 class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>

        {{-- Top Province --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-amber-400 to-amber-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">بیشترین کاربر (استان)</p>
                    <p class="text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-300">{{ $stats['top_province'] ?? '—' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($stats['top_province_count']) }} کاربر</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-3 dark:bg-amber-500/10">
                    <x-heroicon-o-trophy class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </div>
    </div>
```

Note: the old "استان‌های فعال" and "تعداد کل استان‌ها" cards are merged into one card, because "active provinces" is only meaningful next to its 31.

- [ ] **Step 2: Add the coverage note under the map legend**

Insert directly after the `{{-- Legend --}}` block's closing `</div>` (the legend flex row ends before the wrapper `</div>` of `x-data`) and before the line that closes the `x-data` element:

```blade
            {{-- Coverage Note --}}
            @if ($stats['unmapped_users'] > 0)
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($stats['unmapped_users']) }} کاربر آدرس ثبت‌شده دارند ولی استانشان مشخص نیست، بنابراین در نقشه نمایش داده نمی‌شوند.
                </p>
            @endif
```

- [ ] **Step 3: Verify the rendered copy**

Run: `php artisan test --compact tests/Feature/Admin/UserMapDashboardTest.php`
Expected: PASS. Then run `php artisan test --compact tests/Feature/Admin` and confirm the other 56 tests still pass.

- [ ] **Step 4: Check the page in the browser**

Open `/admin/user-map` and confirm (numbers verified against `zioto_stage` with the exact SQL from Task 3):
- card 2 reads `۱٬۸۵۶` with `از ۴٬۷۱۵ کاربر` underneath,
- card 3 reads `۳۱ از ۳۱`,
- card 4 reads `تهران` / `۹۹۶ کاربر`,
- the note reads `۳۷۶ کاربر آدرس ثبت‌شده دارند ولی استانشان مشخص نیست…`,
- hovering and clicking a province still works (tooltip + info panel).

`mapped_users` is 1,856, not 1,855: one user holds both a resolvable and an unresolvable
address, so the old complement formula (`users with address − unmapped`) under-counted by
one. It is also why the denominator under `mapped_users` must be `total_users` and never
`mapped_users + unmapped_users`, which would read 2,232.

These numbers are correct whether or not the Task 2 backfill has run, because Task 3 derives the province in SQL. If the browser still shows the old numbers, the front-end assets are stale — run `npm run build`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/filament/pages/user-map-dashboard.blade.php
git commit -m "feat: state map coverage in the user map stat cards"
```

---

### Task 5: Full verification

**Files:**
- No file changes; verification only.

**Interfaces:**
- Consumes: everything from Tasks 1-4.
- Produces: nothing.

- [ ] **Step 1: Run pint over the whole diff**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"pint":"passed"}`

- [ ] **Step 2: Run the three suites that cover this work**

```bash
php artisan test --compact tests/Feature/Models/UserAddressProvinceTest.php
php artisan test --compact tests/Feature/Commands/BackfillAddressProvincesTest.php
php artisan test --compact tests/Feature/Admin
```

Expected: all green.

- [ ] **Step 3: Ask before running the full suite**

Report the results above to the user and ask whether to run `php artisan test --compact` for the whole application.

---

## Notes / Deliberate Non-Goals

- **The WP importer is not patched.** `ImportUserAddresses::resolveProvinceId()` still returns null for an unmatched `state` meta, but Task 1's hook repairs the row on save, so a future import run produces populated provinces. Patching the importer too would duplicate the same rule in two places.
- **No `COALESCE` fallback inside the read path beyond the join.** `userCountsByProvince()` derives the province in SQL so the page is correct even in an environment where the backfill has not run yet; Task 1 keeps the stored column aligned with it afterwards.
- **Shipping rates keyed by province (`ShippingRate::scopeForProvince`) benefit for free** once the backfill runs — 1,767 users gain a province for rate lookup. No shipping code is touched.
- **`maxUsers` guard stays** (`isEmpty() ? 1 : max()`), so an empty table still yields the lightest colour instead of dividing by zero.
- **The unrelated `LogRolePermissionChanges` bug is out of scope.** `revokePermissionTo()` passes a single Permission model, which the listener turns into "Array to string conversion"; file a separate task for it.
