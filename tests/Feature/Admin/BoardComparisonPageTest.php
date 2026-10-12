<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\BoardComparisonPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BoardComparisonPageTest extends TestCase
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

    private function seedBoard(float $gold750Sell = 270000000.0): void
    {
        Cache::put('zioto:payload', [
            'prices' => [
                'Gold750_Sell' => ['value' => $gold750Sell, 'price_type' => 'sell'],
                'Gold750_Buy' => ['value' => 259000000.0, 'price_type' => 'buy'],
            ],
            'debug' => [],
            'source_status' => [],
            'raw' => ['persian' => [], 'tala' => []],
            'used_fallback' => false,
        ], 60);
    }

    private function fakeTokeniko(array &$requests): void
    {
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => function ($request) use (&$requests) {
                $requests[] = $request;

                return Http::response([
                    'persianDate' => '1405-07-20',
                    'gregorianDate' => '2026-10-12',
                    'lastUpdate' => '2026-10-12 17:05:31',
                    'products' => [
                        ['name' => 'Gold750', 'sellPrice' => 260984724, 'buyPrice' => 259941828],
                        ['name' => 'Gold995', 'sellPrice' => 345893840, 'buyPrice' => 344164371],
                        ['name' => 'Gold9999', 'sellPrice' => 347623309, 'buyPrice' => 345885192],
                        ['name' => 'Silver9999', 'sellPrice' => 5334000, 'buyPrice' => 5173980],
                        ['name' => 'Euro', 'sellPrice' => 2943300, 'buyPrice' => 2943300],
                    ],
                ], 200);
            },
        ]);
    }

    public function test_page_shows_comparison_with_sell_and_buy_differences(): void
    {
        $this->admin();
        $this->seedBoard();

        $requests = [];
        $this->fakeTokeniko($requests);

        $component = Livewire::test(BoardComparisonPage::class)->assertSuccessful();

        $this->assertCount(1, $requests);

        $rows = $component->get('rows');

        $this->assertCount(4, $rows);
        $this->assertSame('Gold750', $rows[0]['name']);
        $this->assertSame(270000000.0, $rows[0]['ourSell']);
        $this->assertSame(260984724.0, $rows[0]['tokSell']);
        $this->assertEqualsWithDelta(
            (270000000.0 - 260984724) / 260984724 * 100,
            $rows[0]['sellDiffPct'],
            0.0001
        );
        // Items missing from our board show nulls instead of breaking.
        $this->assertNull($rows[3]['ourSell']);
        $this->assertSame(5334000.0, $rows[3]['tokSell']);
        $this->assertNull($rows[3]['sellDiffPct']);

        $component->assertSee('مقایسه با توکنیکو', false);
    }

    public function test_refresh_reloads_our_board_and_refetches_tokeniko(): void
    {
        $this->admin();
        $this->seedBoard();

        $requests = [];
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => function ($request) use (&$requests) {
                $requests[] = $request;

                return Http::response([
                    'lastUpdate' => '2026-10-12 17:05:31',
                    'products' => [
                        ['name' => 'Gold750', 'sellPrice' => 260984724, 'buyPrice' => 259941828],
                    ],
                ], 200);
            },
            '*persianapi*' => Http::response(['result' => [
                ['key' => 137120, 'price' => '244259000'],
            ]], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => [
                'geram18k' => ['value' => 24425900],
            ]], 200),
        ]);

        Livewire::test(BoardComparisonPage::class)
            ->call('refresh')
            ->assertSuccessful()
            ->assertSet('tokenikoUpdatedAt', '1405-07-20 17:05');

        $this->assertCount(2, $requests);

        $rows = Livewire::test(BoardComparisonPage::class)->get('rows');

        $this->assertEqualsWithDelta(244259000.0 * 1.001, $rows[0]['ourSell'], 1.0);
    }

    public function test_tokeniko_failure_still_shows_our_board(): void
    {
        $this->admin();
        $this->seedBoard();

        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => Http::response([], 500),
        ]);

        $component = Livewire::test(BoardComparisonPage::class)->assertSuccessful();

        $this->assertNotNull($component->get('tokenikoError'));
        $this->assertSame(270000000.0, $component->get('rows')[0]['ourSell']);
        $this->assertNull($component->get('rows')[0]['tokSell']);
    }
}
