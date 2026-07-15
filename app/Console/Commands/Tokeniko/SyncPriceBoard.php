<?php

namespace App\Console\Commands\Tokeniko;

use App\Events\PriceBoardUpdated;
use App\Events\ProductsUpdated;
use App\Models\Setting;
use App\Services\PriceBoardService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

#[Signature('priceboard:sync')]
#[Description('Fetch prices from Tokeniko, broadcast to clients, and recalculate product prices')]
class SyncPriceBoard extends Command
{
    public function handle(PriceBoardService $priceBoard): int
    {
        $this->info('Syncing price board...');

        $prices = $priceBoard->fetchAndStore();

        if (empty($prices)) {
            $this->warn('No prices received from API or cache.');

            return self::FAILURE;
        }

        $lastSync = $priceBoard->getLastSyncAt();
        $fromApi = $lastSync && $lastSync->diffInSeconds(now()) < 60;

        if (! $fromApi) {
            $this->warn('Using cached prices (API unavailable).');
        }

        broadcast(new PriceBoardUpdated($prices));

        $updated = $this->recalculateProductPrices();

        $this->info("Recalculated prices for {$updated} products.");

        if ($updated > 0) {
            $this->broadcastProducts();
            Cache::flush();
        }

        Log::info('[PriceBoard] Sync completed', [
            'products_updated' => $updated,
            'from_cache' => ! $fromApi,
        ]);

        return self::SUCCESS;
    }

    private function recalculateProductPrices(): int
    {
        $products = Product::whereNotNull('price_board_item')->get();

        $updates = [];

        foreach ($products as $product) {
            $newPrice = $product->calculatePrice();

            if ($newPrice !== null && (float) $product->price !== $newPrice) {
                $updates[$product->id] = ['price' => $newPrice];
                $this->line("  Will update {$product->name}: {$product->price} -> {$newPrice}");
            }
        }

        if (empty($updates)) {
            return 0;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $data) {
                Product::where('id', $id)->update($data);
            }
        });

        return count($updates);
    }

    private function broadcastProducts(): void
    {
        $products = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images'])
            ->whereNotNull('price_board_item')
            ->get()
            ->map(fn (Product $p) => $this->formatProduct($p))
            ->toArray();

        broadcast(new ProductsUpdated($products));
    }

    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;
        $primaryImage = $p->images->firstWhere('is_primary', true) ?? $p->images->first();

        $taxKey = str_starts_with($p->price_board_item ?? '', 'Gold') ? 'tax_gold' : 'tax_silver';
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        return [
            'id' => $p->id,
            'price' => $price,
            'price_before_tax' => $priceBeforeTax,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'old' => null,
        ];
    }
}
