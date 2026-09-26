<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map legacy Tokeniko board item keys to Zioto board keys.
     * Unmapped currencies (Euro, USDollar) become null → fixed price stays.
     */
    private const MAP = [
        'Gold995' => 'Gold995_Sell',
        'Gold999' => 'Gold9999_Sell',
        'Gold9999' => 'Gold9999_Sell',
        'Gold750' => 'Gold750_Sell',
        'Gold705' => 'Gold750_Sell',
        'Silver990' => 'Silver9999_Sell',
        'Silver999' => 'Silver9999_Sell',
        'Silver9999' => 'Silver9999_Sell',
    ];

    public function up(): void
    {
        foreach (self::MAP as $legacy => $zioto) {
            DB::table('products')
                ->where('price_board_item', $legacy)
                ->update(['price_board_item' => $zioto]);
        }

        DB::table('products')
            ->whereIn('price_board_item', ['Euro', 'USDollar', 'Dollar', 'GoldOunce', 'SilverOunce', 'Silver925', 'Silver 925'])
            ->update(['price_board_item' => null]);
    }

    public function down(): void
    {
        $reverse = array_flip(self::MAP);

        foreach ($reverse as $zioto => $legacy) {
            DB::table('products')
                ->where('price_board_item', $zioto)
                ->update(['price_board_item' => $legacy]);
        }
    }
};
