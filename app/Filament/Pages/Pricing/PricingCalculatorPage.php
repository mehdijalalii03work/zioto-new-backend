<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;

class PricingCalculatorPage extends Page
{
    protected static ?string $slug = 'pricing/calculator';

    protected static ?string $title = 'ماشین‌حساب قیمت';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'ماشین‌حساب';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.pricing.pricing-calculator-page';

    public string $spotPrice = '';

    public string $laborPercent = '';

    public string $taxScope = 'labor';

    public string $taxPercent = '10';

    /**
     * @var array{spotPrice: float, laborPercent: float, laborAmount: float, subtotal: float, taxableAmount: float, taxPercent: float, taxAmount: float, totalPrice: float, formattedSpotPrice: string, formattedLaborAmount: string, formattedSubtotal: string, formattedTaxableAmount: string, formattedTaxAmount: string, formattedTotalPrice: string, taxScopeLabel: string}|null
     */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    protected function rules(): array
    {
        return [
            'spotPrice' => ['required', 'numeric', 'gt:0', 'max:999999999999999'],
            'laborPercent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'taxScope' => ['required', Rule::in(['labor', 'product_and_labor'])],
            'taxPercent' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['spotPrice', 'laborPercent', 'taxScope', 'taxPercent'], true)) {
            if ($property !== 'taxScope') {
                $this->{$property} = $this->normalizeNumericInput($this->{$property});
            }

            $this->result = null;
        }
    }

    public function calculate(): void
    {
        $validated = $this->validate();

        $spotPrice = (float) $validated['spotPrice'];
        $laborPercent = (float) $validated['laborPercent'];
        $taxPercent = (float) $validated['taxPercent'];
        $laborAmount = $spotPrice * $laborPercent / 100;
        $subtotal = $spotPrice + $laborAmount;
        $taxableAmount = $validated['taxScope'] === 'labor' ? $laborAmount : $subtotal;
        $taxAmount = $taxableAmount * $taxPercent / 100;
        $totalPrice = $subtotal + $taxAmount;
        $taxScopeLabel = $validated['taxScope'] === 'labor' ? 'فقط اجرت' : 'محصول و اجرت';

        $this->result = [
            'spotPrice' => $spotPrice,
            'laborPercent' => $laborPercent,
            'laborAmount' => $laborAmount,
            'subtotal' => $subtotal,
            'taxableAmount' => $taxableAmount,
            'taxPercent' => $taxPercent,
            'taxAmount' => $taxAmount,
            'totalPrice' => $totalPrice,
            'formattedSpotPrice' => $this->formatAmount($spotPrice),
            'formattedLaborAmount' => $this->formatAmount($laborAmount),
            'formattedSubtotal' => $this->formatAmount($subtotal),
            'formattedTaxableAmount' => $this->formatAmount($taxableAmount),
            'formattedTaxAmount' => $this->formatAmount($taxAmount),
            'formattedTotalPrice' => $this->formatAmount($totalPrice),
            'taxScopeLabel' => $taxScopeLabel,
        ];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 0, '.', '٬');
    }

    private function normalizeNumericInput(string $value): string
    {
        $normalized = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.',
        ]);

        return str_replace(['٬', ',', ' ', "\u{00A0}", "\u{202F}"], '', $normalized);
    }
}
