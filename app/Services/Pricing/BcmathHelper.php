<?php

namespace App\Services\Pricing;

/**
 * Precise decimal arithmetic using BCMath with float fallback.
 */
class BcmathHelper
{
    private const PRECISION = 8;

    public static function isAvailable(): bool
    {
        return function_exists('bcadd');
    }

    public static function add(string|float $left, string|float $right, ?int $scale = null): string
    {
        $scale ??= self::PRECISION;

        if (self::isAvailable()) {
            return bcadd((string) $left, (string) $right, $scale);
        }

        return (string) ((float) $left + (float) $right);
    }

    public static function sub(string|float $left, string|float $right, ?int $scale = null): string
    {
        $scale ??= self::PRECISION;

        if (self::isAvailable()) {
            return bcsub((string) $left, (string) $right, $scale);
        }

        return (string) ((float) $left - (float) $right);
    }

    public static function mul(string|float $left, string|float $right, ?int $scale = null): string
    {
        $scale ??= self::PRECISION;

        if (self::isAvailable()) {
            return bcmul((string) $left, (string) $right, $scale);
        }

        return (string) ((float) $left * (float) $right);
    }

    public static function div(string|float $left, string|float $right, ?int $scale = null): string
    {
        $scale ??= self::PRECISION;

        if ((string) $right === '0' || (float) $right === 0.0) {
            return '0';
        }

        if (self::isAvailable()) {
            return bcdiv((string) $left, (string) $right, $scale);
        }

        return (string) ((float) $left / (float) $right);
    }

    public static function comp(string|float $left, string|float $right, ?int $scale = null): int
    {
        $scale ??= self::PRECISION;

        if (self::isAvailable()) {
            return bccomp((string) $left, (string) $right, $scale);
        }

        $leftVal = (float) $left;
        $rightVal = (float) $right;

        if ($leftVal === $rightVal) {
            return 0;
        }

        return $leftVal > $rightVal ? 1 : -1;
    }

    public static function max(string|float $left, string|float $right, ?int $scale = null): string
    {
        return self::comp($left, $right, $scale) >= 0 ? (string) $left : (string) $right;
    }

    public static function min(string|float $left, string|float $right, ?int $scale = null): string
    {
        return self::comp($left, $right, $scale) <= 0 ? (string) $left : (string) $right;
    }

    public static function isPositive(mixed $value): bool
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return false;
        }

        return self::comp((string) $value, '0') > 0;
    }

    public static function toFloat(string|float $value): float
    {
        return (float) $value;
    }
}
