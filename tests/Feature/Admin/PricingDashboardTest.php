<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Pricing\PricingDashboard;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value, string $type = 'number'): void
    {
        Setting::create([
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'category' => 'pricing',
            'label' => $key,
        ]);
    }

    public function test_emergency_table_is_empty_when_mode_is_disabled(): void
    {
        $this->setting('zioto_pricing_coef_emergency_gold', '1.02');
        $this->setting('zioto_pricing_coef_emergency_silver', '0.98');

        $board = [
            'Gold750_Sell' => ['value' => 102000.0],
            'Silver9999_Sell' => ['value' => 98000.0],
        ];

        $this->assertSame([], PricingDashboard::buildEmergencyTable($board));
    }

    public function test_emergency_table_restores_exact_pre_emergency_values(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_emergency_mode_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'emergency_mode_enabled',
        ]);
        $this->setting('zioto_pricing_coef_emergency_gold', '1.02');
        $this->setting('zioto_pricing_coef_emergency_silver', '0.98');

        $board = [
            'Gold750_Sell' => ['value' => 102000.0],
            'Silver9999_Sell' => ['value' => 98000.0],
        ];

        $rows = PricingDashboard::buildEmergencyTable($board);

        $this->assertCount(2, $rows);

        $this->assertSame('Gold750_Sell', $rows[0]['key']);
        $this->assertEqualsWithDelta(100000.0, $rows[0]['base'], 0.001);
        $this->assertSame(102000.0, $rows[0]['final']);
        $this->assertEqualsWithDelta(2000.0, $rows[0]['diff'], 0.001);
        $this->assertEqualsWithDelta(2.0, $rows[0]['pct'], 0.001);

        $this->assertSame('Silver9999_Sell', $rows[1]['key']);
        $this->assertEqualsWithDelta(100000.0, $rows[1]['base'], 0.001);
        $this->assertEqualsWithDelta(-2000.0, $rows[1]['diff'], 0.001);
        $this->assertEqualsWithDelta(-2.0, $rows[1]['pct'], 0.001);
    }

    public function test_emergency_table_skips_keys_missing_from_the_board(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_emergency_mode_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'emergency_mode_enabled',
        ]);

        $rows = PricingDashboard::buildEmergencyTable(['Gold750_Sell' => ['value' => 100.0]]);

        $this->assertCount(1, $rows);
        $this->assertSame('Gold750_Sell', $rows[0]['key']);
    }
}
