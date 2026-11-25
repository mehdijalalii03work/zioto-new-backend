<?php

namespace App\Exceptions;

use Modules\Product\Models\Product;
use RuntimeException;

class RahkaranSerialConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $serial,
        public readonly Product $conflictingProduct,
    ) {
        parent::__construct('سریال راهکاران قبلاً برای کالای دیگری ثبت شده است.');
    }
}
