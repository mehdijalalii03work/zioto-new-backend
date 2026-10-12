<?php

namespace App\Payment\Exceptions;

use Shetabit\Multipay\Exceptions\InvalidPaymentException;

/** A provider error is diagnostic information, not evidence that money failed. */
class AmbiguousGatewayOutcomeException extends InvalidPaymentException
{
    /** @param array<string, mixed> $providerResponse */
    public function __construct(
        public readonly string $gateway,
        public readonly int $httpStatus,
        public readonly int|string|null $providerCode,
        public readonly array $providerResponse,
    ) {
        parent::__construct(is_string($providerResponse['result']['message'] ?? null) ? $providerResponse['result']['message'] : 'نتیجه درگاه هنوز مشخص نیست.');
    }
}
