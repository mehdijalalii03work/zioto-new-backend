<?php

namespace App\Services\Inventory;

use RuntimeException;

final class SerialImportException extends RuntimeException
{
    public static function fileError(string $message): self
    {
        return new self($message);
    }

    public static function publicMessage(\Throwable $exception): string
    {
        return $exception instanceof self ? $exception->getMessage() : 'پردازش فایل با خطا مواجه شد؛ لطفاً دوباره تلاش کنید.';
    }

    public static function mappingChanged(): self
    {
        return new self('نگاشت پیشوندها پس از تحلیل تغییر کرده است؛ لطفاً تحلیل تازه انجام دهید.');
    }
}
