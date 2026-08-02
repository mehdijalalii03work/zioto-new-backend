<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the current tenant ("platform") for the request.
 *
 * Platforms:
 *   - 'main'  → sawiss.com (main storefront)
 *   - 'nopay' → pay.sawiss.com (BMIC installment landing)
 *
 * The frontend sends an `X-Platform` header on every API call. Anything
 * unknown or missing falls back to 'main' so existing clients keep working.
 */
class Platform
{
    public const MAIN = 'main';

    public const NOPAY = 'nopay';

    public const ALL = [self::MAIN, self::NOPAY];

    public static function fromRequest(?Request $request = null): string
    {
        $request ??= request();

        $platform = $request?->header('X-Platform');

        if ($platform === null || ! in_array($platform, self::ALL, true)) {
            return self::MAIN;
        }

        return $platform;
    }

    public static function isNopay(?Request $request = null): bool
    {
        return self::fromRequest($request) === self::NOPAY;
    }
}
