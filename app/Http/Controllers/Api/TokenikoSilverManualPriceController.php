<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Pricing\PricingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenikoSilverManualPriceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.tokeniko.silver_price_secret', '');

        if ($secret === '') {
            return response()->json(['message' => 'Tokeniko pricing API is not configured'], 503);
        }

        $providedSecret = $request->bearerToken();

        if (! is_string($providedSecret) || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'price_rial' => ['required', 'integer', 'min:1000000', 'max:999999999999999'],
        ]);
        $priceRial = (string) $validated['price_rial'];
        $priceToman = bcdiv($priceRial, '10', 1);
        if (str_ends_with($priceToman, '.0')) {
            $priceToman = substr($priceToman, 0, -2);
        }
        $key = 'zioto_pricing_manual_silver9999_sell';
        $setting = Setting::query()->where('key', $key)->first();
        $updated = $setting === null || bccomp((string) $setting->value, $priceToman, 1) !== 0;

        if ($updated) {
            $request->attributes->set('pricing_audit_actor_name', 'توکنیکو (API)');

            Setting::updateOrCreate(['key' => $key], [
                'value' => $priceToman,
                'type' => 'number',
                'category' => 'pricing',
                'label' => 'manual_silver9999_sell',
            ]);
        }

        PricingSettings::invalidateBoardCache();

        return response()->json([
            'price_rial' => (int) $priceRial,
            'updated' => $updated,
        ]);
    }
}
