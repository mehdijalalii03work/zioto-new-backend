<?php

namespace App\Services\Inventory;

/**
 * Maps a raw factory serial to a product via longest-prefix match.
 *
 * Contract (plan §2.3):
 * - Case-insensitive; Persian/Arabic digits are converted to ASCII.
 * - Only leading/trailing whitespace is trimmed. Internal spaces,
 *   formulas and pure numbers are rejected as invalid_format.
 * - The longest known prefix wins (ton9 over ton). When the matched
 *   product is not tracked, the result is product_not_tracked and there
 *   is deliberately NO fallback to a shorter prefix.
 */
final class SerialPrefixResolver
{
    public const MAX_LENGTH = 50;

    /**
     * @param  array<int, array{product_id: int, prefix: string, track_serials: bool}>  $mappings
     */
    public static function resolve(string $raw, array $mappings): SerialResolution
    {
        $normalized = self::normalize($raw);

        if ($normalized === '') {
            return new SerialResolution('empty', '');
        }

        if (strlen($normalized) > self::MAX_LENGTH || ! preg_match('/\A[a-z]{3}9?[0-9]+\z/', $normalized)) {
            return new SerialResolution('invalid_format', $normalized);
        }

        $sorted = $mappings;
        usort($sorted, fn (array $a, array $b): int => strlen($b['prefix']) <=> strlen($a['prefix']));

        foreach ($sorted as $mapping) {
            $prefix = strtolower($mapping['prefix']);

            if (! str_starts_with($normalized, $prefix)) {
                continue;
            }

            if (strlen($normalized) === strlen($prefix)) {
                return new SerialResolution('invalid_format', $normalized, $prefix, $mapping['product_id']);
            }

            if (! $mapping['track_serials']) {
                return new SerialResolution('product_not_tracked', $normalized, $prefix, $mapping['product_id']);
            }

            return new SerialResolution('valid', $normalized, $prefix, $mapping['product_id']);
        }

        return new SerialResolution('unknown_prefix', $normalized);
    }

    public static function normalize(string $raw): string
    {
        $converted = strtr(trim($raw), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return strtolower($converted);
    }
}
