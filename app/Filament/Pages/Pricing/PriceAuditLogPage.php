<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Models\PriceAuditLog;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

class PriceAuditLogPage extends Page
{
    use WithPagination;

    protected static ?string $slug = 'pricing/audit-logs';

    protected static ?string $title = 'تاریخچه تغییرات قیمت';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'تاریخچه تغییرات';

    protected static string|\UnitEnum|null $navigationGroup = 'تابلو قیمت زیوتو';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.pricing.price-audit-logs';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    public function getRecords(): LengthAwarePaginator
    {
        return PriceAuditLog::query()
            ->with('actor:id,name,first_name,last_name')
            ->latest()
            ->paginate(50);
    }

    public function formatCreatedAt(PriceAuditLog $record): string
    {
        return $record->created_at
            ? Jalalian::fromCarbon($record->created_at)->format('H:i:s Y/m/d')
            : '—';
    }

    public function actorName(PriceAuditLog $record): string
    {
        if (! $record->actor) {
            return $record->actor_name ?? 'سیستم';
        }

        return $record->actor->first_name
            ? trim($record->actor->first_name.' '.$record->actor->last_name)
            : $record->actor->name;
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'value' => 'مقدار',
            'price' => 'قیمت محصول',
            'price_type' => 'نوع قیمت‌گذاری',
            'price_board_item' => 'آیتم تابلو',
            'weight' => 'وزن',
            'fee_off_hours' => 'اجرت خارج از ساعات کاری',
            'fee_business_hours' => 'اجرت ساعات کاری',
            'labor_coefficients' => 'ضرایب اجرت',
            'manual_gold750_sell' => 'قیمت دستی فروش طلای ۷۵۰',
            'manual_gold999_sell' => 'قیمت دستی فروش طلای ۹۹۹',
            'manual_silver9999_sell' => 'قیمت دستی فروش نقره ۹۹۹.۹',
            'emergency_mode_enabled' => 'حالت اضطراری',
            'tax_gold_labor' => 'مالیات اجرت طلا',
            'round_prices' => 'گردکردن قیمت',
            'round_to' => 'مضرب گردکردن قیمت',
            'silver_round_to' => 'مضرب گردکردن نقره',
            'enable_dynamic_pricing' => 'قیمت‌گذاری پویا',
            'labor_roles' => 'نقش‌های اجرت',
            'time_periods' => 'بازه‌های زمانی اجرت',
            'rounding_mode' => 'روش گردکردن قیمت',
            'coef_gold750_sell_ratio' => 'ضریب فروش طلای ۷۵۰',
            'coef_gold750_buy_ratio' => 'ضریب خرید طلای ۷۵۰',
            'coef_gold999_sell_ratio' => 'ضریب فروش طلای ۹۹۹',
            'coef_gold999_buy_ratio' => 'ضریب خرید طلای ۹۹۹',
            'coef_gold999_to_gold995' => 'ضریب تبدیل طلای ۹۹۹ به ۹۹۵',
            'coef_gold995_sell_ratio' => 'ضریب فروش طلای ۹۹۵',
            'coef_gold995_buy_ratio' => 'ضریب خرید طلای ۹۹۵',
            'coef_gold999_to_gold9999' => 'ضریب تبدیل طلای ۹۹۹ به ۹۹۹.۹',
            'coef_gold9999_sell_ratio' => 'ضریب فروش طلای ۹۹۹.۹',
            'coef_gold9999_buy_ratio' => 'ضریب خرید طلای ۹۹۹.۹',
            'coef_silver9999_to_silver999' => 'ضریب تبدیل نقره ۹۹۹.۹ به ۹۹۹',
            'coef_silver_buy_price' => 'ضریب خرید نقره',
            'coef_buy_price' => 'ضریب خرید طلا',
            'coef_emergency_gold' => 'ضریب اضطرار طلا',
            'coef_emergency_silver' => 'ضریب اضطرار نقره',
            default => $field,
        };
    }

    public function subjectLabel(PriceAuditLog $record): string
    {
        $label = (string) ($record->subject_label ?? $record->subject_type);
        $key = str_starts_with($label, 'zioto_pricing_') ? substr($label, strlen('zioto_pricing_')) : $label;

        return $this->fieldLabel($key);
    }

    public function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—';
        }

        if (is_bool($value)) {
            return $value ? 'فعال' : 'غیرفعال';
        }

        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
