<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\UserMapDashboard;
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

        $this->assertSame('fill: rgb(255, 0, 0)', $shapes['tehran']['fill']);
        $this->assertSame('fill: rgb(255, 200, 200)', $shapes['yazd']['fill']);

        $this->assertSame(5, substr_count($html, 'background-color: rgb('));
        $this->assertStringContainsString('background-color: rgb(255, 200, 200)', $html);
        $this->assertStringContainsString('background-color: rgb(255, 0, 0)', $html);
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
        $this->assertSame('fill: rgb(255, 0, 0)', $shapes['fars']['fill']);
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
