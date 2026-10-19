<?php

namespace App\Observers;

use App\Models\PriceAuditLog;
use Modules\Product\Models\Product;

class ProductPricingAuditObserver
{
    private const PRICE_FIELDS = [
        'price',
        'price_type',
        'price_board_item',
        'weight',
        'fee_off_hours',
        'fee_business_hours',
        'labor_coefficients',
    ];

    public function created(Product $product): void
    {
        $changes = [];

        foreach (self::PRICE_FIELDS as $field) {
            if ($product->getAttribute($field) !== null) {
                $changes[$field] = ['old' => null, 'new' => $product->getAttribute($field)];
            }
        }

        $this->record($product, $changes);
    }

    public function updated(Product $product): void
    {
        $changes = [];

        foreach (self::PRICE_FIELDS as $field) {
            if ($product->wasChanged($field)) {
                $changes[$field] = [
                    'old' => $product->getOriginal($field),
                    'new' => $product->getAttribute($field),
                ];
            }
        }

        $this->record($product, $changes);
    }

    public function deleted(Product $product): void
    {
        $changes = [];

        foreach (self::PRICE_FIELDS as $field) {
            if ($product->getAttribute($field) !== null) {
                $changes[$field] = ['old' => $product->getAttribute($field), 'new' => null];
            }
        }

        $this->record($product, $changes);
    }

    private function record(Product $product, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        PriceAuditLog::create([
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->first_name
                ? trim(auth()->user()->first_name.' '.auth()->user()->last_name)
                : auth()->user()?->name,
            'subject_type' => 'product',
            'subject_id' => $product->getKey(),
            'subject_label' => $product->name,
            'changes' => $changes,
        ]);
    }
}
