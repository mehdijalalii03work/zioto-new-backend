<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Products available for nopay (BMIC installment) purchases on pay.sawiss.com.
     *
     * The 9 storefront slugs are the ones listed on the nopay landing page;
     * 'test' is the private sandbox product used to verify the checkout flow.
     */
    private const NOPAY_PRODUCT_SLUGS = [
        'zioto-gold-bar-0-5g-995',
        'zioto-gold-bar-1g-995',
        'zioto-plus-gold-bar-1g-9999',
        'zioto-silver-bar-2-5g-999-9',
        'zioto-silver-bar-5g-999-9',
        'zioto-silver-bar-10g-999-9',
        'zioto-silver-bar-15g-999-9',
        'zioto-silver-bar-1oz-999-9',
        'zioto-silver-bar-50g-999-9',
        'test',
    ];

    public function up(): void
    {
        DB::table('products')
            ->whereIn('slug', self::NOPAY_PRODUCT_SLUGS)
            ->update(['is_nopay' => true]);
    }

    public function down(): void
    {
        DB::table('products')
            ->whereIn('slug', self::NOPAY_PRODUCT_SLUGS)
            ->update(['is_nopay' => false]);
    }
};
