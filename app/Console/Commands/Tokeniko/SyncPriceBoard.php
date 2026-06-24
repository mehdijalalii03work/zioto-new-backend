<?php

namespace App\Console\Commands\Tokeniko;

use App\Events\PriceBoardUpdated;
use App\Services\PriceBoardService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
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
            $this->warn('No prices received.');

            return self::FAILURE;
        }

        broadcast(new PriceBoardUpdated($prices));

        $this->info('Price board synced and broadcasted.');

        $updated = $this->recalculateProductPrices();

        $this->info("Recalculated prices for {$updated} products.");

        Log::info('[PriceBoard] Sync completed', ['products_updated' => $updated]);

        return self::SUCCESS;
    }

    private function recalculateProductPrices(): int
    {
        $products = Product::whereNotNull('price_board_item')->get();
        $updated = 0;

        foreach ($products as $product) {
            $newPrice = $product->calculatePrice();

            if ($newPrice !== null && (float) $product->price !== $newPrice) {
                $product->update(['price' => $newPrice]);
                $updated++;
                $this->line("  Updated {$product->name}: {$product->price} -> {$newPrice}");
            }
        }

        return $updated;
    }
}
