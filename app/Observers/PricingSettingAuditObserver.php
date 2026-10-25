<?php

namespace App\Observers;

use App\Models\PriceAuditLog;
use App\Models\Setting;

class PricingSettingAuditObserver
{
    public function created(Setting $setting): void
    {
        if ($this->isPriceSetting($setting->key)) {
            $this->record($setting, null, $setting->value);
        }
    }

    public function updated(Setting $setting): void
    {
        if ($this->isPriceSetting($setting->key) && $setting->wasChanged('value')) {
            $this->record($setting, $setting->getOriginal('value'), $setting->value);
        }
    }

    public function deleted(Setting $setting): void
    {
        if ($this->isPriceSetting($setting->key)) {
            $this->record($setting, $setting->value, null);
        }
    }

    private function record(Setting $setting, mixed $oldValue, mixed $newValue): void
    {
        PriceAuditLog::create([
            'actor_id' => auth()->id(),
            'actor_ip' => request()->ip(),
            'actor_name' => auth()->user()?->first_name
                ? trim(auth()->user()->first_name.' '.auth()->user()->last_name)
                : auth()->user()?->name,
            'subject_type' => 'setting',
            'subject_id' => $setting->getKey(),
            'subject_label' => $setting->label ?: $setting->key,
            'changes' => ['value' => ['old' => $oldValue, 'new' => $newValue]],
        ]);
    }

    private function isPriceSetting(string $key): bool
    {
        return str_starts_with($key, 'zioto_pricing_coef_')
            || str_starts_with($key, 'zioto_pricing_manual_')
            || in_array($key, [
                'zioto_pricing_labor_roles',
                'zioto_pricing_time_periods',
                'zioto_pricing_emergency_mode_enabled',
                'zioto_pricing_enable_dynamic_pricing',
                'zioto_pricing_force_nightly_pricing',
                'zioto_pricing_persian_api_enabled',
                'zioto_pricing_tala_api_enabled',
                'zioto_pricing_tokeniko_board_enabled',
                'zioto_pricing_round_prices',
                'zioto_pricing_round_to',
                'zioto_pricing_rounding_mode',
                'zioto_pricing_silver_round_to',
                'tax_gold_labor',
            ], true);
    }
}
