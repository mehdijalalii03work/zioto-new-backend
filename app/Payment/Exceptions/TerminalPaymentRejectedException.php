<?php

namespace App\Payment\Exceptions;

use Shetabit\Multipay\Exceptions\InvalidPaymentException;

/**
 * Only an adapter with validated server-to-server evidence of a documented,
 * terminal rejection may emit this outcome. Unsigned callback fields, HTTP
 * errors, authentication errors, missing data and unknown provider states
 * cannot establish it. Current provider adapters make no unverified mapping.
 */
class TerminalPaymentRejectedException extends InvalidPaymentException
{
    /** @param array<string, mixed> $providerEvidence */
    public function __construct(
        public readonly string $gateway,
        public readonly string $providerCode,
        public readonly array $providerEvidence,
    ) {
        parent::__construct('درگاه عدم پرداخت را به‌طور قطعی تأیید کرد.');
    }
}
