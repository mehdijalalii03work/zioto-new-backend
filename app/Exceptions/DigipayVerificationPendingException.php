<?php

namespace App\Exceptions;

use RuntimeException;

class DigipayVerificationPendingException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly ?int $providerStatus = null)
    {
        parent::__construct($message, $httpStatus ?? 0);
    }
}
