<?php

namespace App\Enums\Inventory;

enum SerialImportBatchStatus: string
{
    case Analyzing = 'analyzing';
    case Ready = 'ready';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Analyzing => 'در حال تحلیل',
            self::Ready => 'آماده اجرا',
            self::Queued => 'در صف',
            self::Processing => 'در حال پردازش',
            self::Completed => 'تکمیل شده',
            self::CompletedWithErrors => 'تکمیل شده با خطا',
            self::Failed => 'ناموفق',
        };
    }
}
