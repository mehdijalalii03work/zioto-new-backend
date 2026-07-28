<?php

namespace App\Console\Commands;

use App\Models\OrderShipping;
use App\Models\ShippingRate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:fill-shipping-taxes')]
#[Description('محاسبه tax_amount و tax_rate برای رکوردهای shipping که مقدار null دارند')]
class FillOrderShippingTaxes extends Command
{
    public function handle(): int
    {
        $shippings = OrderShipping::whereNull('tax_amount')
            ->orWhereNull('tax_rate')
            ->get();

        if ($shippings->isEmpty()) {
            $this->info('همه رکوردها دارای tax_amount و tax_rate هستند.');

            return self::SUCCESS;
        }

        $this->info("{$shippings->count()} رکورد نیاز به بروزرسانی دارند.");
        $updated = 0;

        foreach ($shippings as $shipping) {
            $rate = ShippingRate::where('shipping_method_id', $shipping->shipping_method_id)->first();

            if (! $rate || ! $rate->tax_rate) {
                $shipping->updateQuietly([
                    'tax_amount' => 0,
                    'tax_rate' => 0,
                ]);
                $updated++;

                continue;
            }

            $totalCost = (int) $shipping->shipping_cost;
            $taxRate = (float) $rate->tax_rate;
            $taxAmount = (int) round($totalCost * $taxRate / (100 + $taxRate));

            $shipping->updateQuietly([
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
            ]);
            $updated++;

            $this->line("  shipping #{$shipping->id}: cost={$totalCost}, rate={$taxRate}%, tax={$taxAmount}");
        }

        $this->info("بروزرسانی کامل شد. {$updated} رکورد تغییر یافتند.");

        return self::SUCCESS;
    }
}
