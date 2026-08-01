<?php

namespace App\Services;

class InstallmentService
{
    public const GATEWAYS = ['digipay', 'smartis', 'kamanlend', 'nopay'];

    public const FEE_GATEWAYS = ['digipay', 'smartis', 'kamanlend'];

    public const FEE_PERCENT = 4;

    public static function isInstallmentGateway(string $gateway): bool
    {
        return in_array($gateway, self::GATEWAYS);
    }

    public static function isFeeGateway(string $gateway): bool
    {
        return in_array($gateway, self::FEE_GATEWAYS);
    }

    public static function calculateFee(int $baseAmount): int
    {
        if ($baseAmount <= 0) {
            return 0;
        }

        return (int) round($baseAmount * self::FEE_PERCENT / 100);
    }
}
