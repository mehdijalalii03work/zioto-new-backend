<?php

namespace App\Jobs;

use App\Enums\Inventory\SerialImportBatchStatus;
use App\Models\SerialImportBatch;
use App\Services\Inventory\SerialImportException;
use App\Services\Inventory\SerialImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessSerialImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $batchId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('serial-import-'.$this->batchId))->shared()->releaseAfter(10)->expireAfter(70)];
    }

    /**
     * Execute the job.
     */
    public function handle(SerialImportService $service): void
    {
        $service->import($this->batchId);
    }

    /**
     * Record a sanitized failure on the batch (never leaks paths/SQL).
     */
    public function failed(Throwable $e): void
    {
        report($e);
        DB::transaction(function () use ($e): void {
            $batch = SerialImportBatch::lockForUpdate()->find($this->batchId);

            if ($batch === null || empty($batch->mapping_snapshot['mapping_hash']) || in_array($batch->status, [
                SerialImportBatchStatus::Completed,
                SerialImportBatchStatus::CompletedWithErrors,
            ], true)) {
                return;
            }

            $batch->update([
                'status' => SerialImportBatchStatus::Failed->value,
                'last_error' => SerialImportException::publicMessage($e),
                'completed_at' => now(),
            ]);
        });
    }
}
