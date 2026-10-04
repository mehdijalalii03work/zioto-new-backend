<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\UserMapDashboard;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserMapDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ProvinceSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(Role::Admin->value);
    }

    private function province(string $slug): Province
    {
        return Province::where('slug', $slug)->firstOrFail();
    }

    /**
     * Every province shape rendered on the map, keyed by province slug.
     *
     * @return array<string, array{fill: string, name: string, count: int, d: string}>
     */
    private function mapShapes(string $html): array
    {
        $dom = new DOMDocument;
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        $shapes = [];

        foreach ((new DOMXPath($dom))->query('//path[@data-province-slug]') as $path) {
            /** @var DOMElement $path */
            $shapes[$path->getAttribute('data-province-slug')] = [
                'fill' => $path->getAttribute('style'),
                'name' => $path->getAttribute('data-province-name'),
                'count' => (int) $path->getAttribute('data-user-count'),
                'd' => $path->getAttribute('d'),
            ];
        }

        return $shapes;
    }

    /**
     * The three channels of a shape's `fill: rgb(r, g, b)` style.
     *
     * @return array{int, int, int}
     */
    private function fillChannels(string $fill): array
    {
        $this->assertSame(1, preg_match('/^fill: rgb\((\d+), (\d+), (\d+)\)$/', $fill, $channels));

        return [(int) $channels[1], (int) $channels[2], (int) $channels[3]];
    }

    public function test_it_renders_a_shape_with_geometry_for_every_province(): void
    {
        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->assertSee('توزیع کاربران بر اساس استان')
            ->html();

        $shapes = $this->mapShapes($html);

        $this->assertCount(31, $shapes);
        $this->assertSame(31, substr_count($html, '</text>'));

        foreach (Province::all() as $province) {
            $shape = $shapes[$province->slug] ?? null;

            $this->assertNotNull($shape, "Missing map shape for {$province->slug}.");
            $this->assertSame($province->name, $shape['name']);
            $this->assertStringStartsWith('m', $shape['d']);
            $this->assertStringContainsString(' l', $shape['d']);
            $this->assertStringEndsWith('z', $shape['d']);
            $this->assertGreaterThan(100, strlen($shape['d']));
        }
    }

    public function test_it_colours_the_map_by_user_count(): void
    {
        $busy = User::factory()->create();
        $quiet = User::factory()->create();

        UserAddress::factory()->create([
            'user_id' => $busy->id,
            'province_id' => $this->province('tehran')->id,
            'city_id' => null,
        ]);
        UserAddress::factory()->create([
            'user_id' => $quiet->id,
            'province_id' => $this->province('alborz')->id,
            'city_id' => null,
        ]);

        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->assertSee('تهران')
            ->html();

        $shapes = $this->mapShapes($html);

        $this->assertSame(1, $shapes['tehran']['count']);
        $this->assertSame(1, $shapes['alborz']['count']);
        $this->assertSame(0, $shapes['yazd']['count']);

        $this->assertSame('fill: rgb(92, 0, 0)', $shapes['tehran']['fill']);
        $this->assertSame('fill: rgb(240, 190, 180)', $shapes['yazd']['fill']);

        $this->assertSame(5, substr_count($html, 'background-color: rgb('));
        $this->assertStringContainsString('background-color: rgb(240, 190, 180)', $html);
        $this->assertStringContainsString('background-color: rgb(92, 0, 0)', $html);
    }

    public function test_the_colour_ramp_darkens_monotonically_with_user_count(): void
    {
        UserAddress::factory()->count(4)->create([
            'province_id' => $this->province('tehran')->id,
            'city_id' => null,
        ]);

        UserAddress::factory()->create([
            'user_id' => User::factory()->create()->id,
            'province_id' => $this->province('fars')->id,
            'city_id' => null,
        ]);

        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->html();

        $shapes = $this->mapShapes($html);

        $this->assertSame(4, $shapes['tehran']['count']);
        $this->assertSame(1, $shapes['fars']['count']);
        $this->assertSame(0, $shapes['yazd']['count']);

        $busiest = $this->fillChannels($shapes['tehran']['fill']);
        $middle = $this->fillChannels($shapes['fars']['fill']);
        $empty = $this->fillChannels($shapes['yazd']['fill']);

        foreach (['red', 'green', 'blue'] as $index => $channel) {
            $this->assertLessThan(
                $middle[$index],
                $busiest[$index],
                "Expected the busiest province to be darker in {$channel} than one holding an intermediate count.",
            );

            $this->assertLessThan(
                $empty[$index],
                $middle[$index],
                "Expected one holding an intermediate count to be darker in {$channel} than an empty province.",
            );
        }

        // Only the deepest fill carries the label variant that inverts the halo.
        $this->assertMatchesRegularExpression(
            '/<text[^>]*class="iran-province-labels--on-dark"[^>]*>'.preg_quote($shapes['tehran']['name'], '/').'<\/text>/u',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '/<text[^>]*class=""[^>]*>'.preg_quote($shapes['yazd']['name'], '/').'<\/text>/u',
            $html,
        );
    }

    public function test_it_counts_a_user_once_per_province(): void
    {
        $user = User::factory()->create();
        $province = $this->province('fars');

        UserAddress::factory()->count(2)->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'city_id' => null,
        ]);

        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->html();

        $shapes = $this->mapShapes($html);

        $this->assertSame(1, $shapes['fars']['count']);
        $this->assertSame('fill: rgb(92, 0, 0)', $shapes['fars']['fill']);
    }

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

        $component = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful();

        $shapes = $this->mapShapes($component->html());

        $this->assertSame(1, $shapes['fars']['count']);
        $this->assertSame('fill: rgb(92, 0, 0)', $shapes['fars']['fill']);

        $stats = $component->instance()->stats;

        $this->assertSame(1, $stats['mapped_users']);
        $this->assertSame(0, $stats['unmapped_users']);
    }

    public function test_a_user_with_both_a_mapped_and_an_unmappable_address_is_counted_as_mapped(): void
    {
        $province = $this->province('yazd');
        $user = User::factory()->create();

        UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'city_id' => null,
        ]);

        UserAddress::withoutEvents(fn () => UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]));

        $stats = Livewire::actingAs($this->admin(), 'web')
            ->test(UserMapDashboard::class)
            ->assertSuccessful()
            ->instance()
            ->stats;

        $this->assertSame(1, $stats['mapped_users']);
        $this->assertSame(1, $stats['unmapped_users']);
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

    public function test_it_requires_the_dashboard_permission(): void
    {
        $staff = User::factory()->create();

        $this->assertFalse($staff->hasPermissionTo(Permission::DashboardView->value));

        Livewire::actingAs($staff, 'web')
            ->test(UserMapDashboard::class)
            ->assertForbidden();
    }
}
