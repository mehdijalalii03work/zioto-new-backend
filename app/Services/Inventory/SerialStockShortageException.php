<?php

namespace App\Services\Inventory;

use RuntimeException;

final class SerialStockShortageException extends RuntimeException
{
    public function __construct(
        private readonly ?int $productId,
        private readonly int $needed,
        private readonly int $available,
    ) {
        parent::__construct('موجودی سریال برای تخصیص کافی نیست.');
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function getNeeded(): int
    {
        return $this->needed;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }
}
