<?php

namespace App\Console\Commands\Tokeniko;

use App\Events\PriceBoardUpdated;
use App\Services\PriceBoardService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('priceboard:sync')]
#[Description('Fetch prices from Tokeniko and broadcast to connected clients')]
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

        $this->info('Price board synced and broadcasted to '.count($prices).' items.');

        return self::SUCCESS;
    }
}
