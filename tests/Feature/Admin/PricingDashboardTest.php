<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\PricingDashboard;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PricingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function admin(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web');
    }

    private function enableTokenikoMode(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_tokeniko_board_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'tokeniko_board_enabled',
        ]);
    }

    private function fakeTokeniko(): void
    {
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => Http::response(['products' => [
                ['name' => 'Gold750', 'sellPrice' => 260000000, 'buyPrice' => 259000000],
                ['name' => 'Gold9999', 'sellPrice' => 348000000, 'buyPrice' => 346000000],
                ['name' => 'Euro', 'sellPrice' => 2943300, 'buyPrice' => 2943300],
            ]], 200),
        ]);
    }

    public function test_dashboard_shows_tokeniko_mode_with_passthrough_values(): void
    {
        $this->admin();
        $this->enableTokenikoMode();
        $this->fakeTokeniko();

        $component = Livewire::test(PricingDashboard::class)->assertSuccessful();

        $formula = $component->get('formula');

        $this->assertTrue($formula['tokenikoMode'] ?? false);
        $this->assertSame(260000000.0, $formula['gold750']['sell']);
        $this->assertSame(259000000.0, $formula['gold750']['buy']);
        $this->assertSame('توکنیکو مستقیم', $formula['gold750']['sellLogic']);

        $component
            ->assertSee('منبع تابلو: توکنیکو', false)
            ->assertSee('توکنیکو مستقیم (بدون ضریب و دستی)', false);
    }

    public function test_dashboard_computed_mode_has_no_tokeniko_flag(): void
    {
        $this->admin();

        Http::fake([
            '*persianapi*' => Http::response(['result' => [
                ['key' => 137120, 'price' => '244259000'],
            ]], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => [
                'geram18k' => ['value' => 24425900],
            ]], 200),
        ]);

        $component = Livewire::test(PricingDashboard::class)->assertSuccessful();

        $formula = $component->get('formula');

        $this->assertFalse(! empty($formula['tokenikoMode']));
        $this->assertArrayHasKey('gold750', $formula);
    }
}
