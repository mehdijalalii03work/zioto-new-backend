<?php

namespace App\Services\Inventory;

/**
 * Outcome of resolving one raw serial value against known prefixes.
 *
 * Status is one of: valid, empty, invalid_format, unknown_prefix,
 * product_not_tracked.
 */
final readonly class SerialResolution
{
    public function __construct(
        public string $status,
        public string $normalized,
        public ?string $prefix = null,
        public ?int $productId = null,
    ) {}
}
