<?php

use App\Enums\Permission;
use App\Models\SerialImportBatch;
use App\Services\Inventory\SerialImportReportService;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('filament.admin.auth.login'));

Route::middleware(['web', Authenticate::class])->prefix('admin')->group(function () {
    Route::get('serial-import-batches/{batch}/report', function (SerialImportBatch $batch) {
        abort_unless(auth()->user()?->checkPermissionTo(Permission::SerialImport->value), 403);

        return app(SerialImportReportService::class)->download($batch);
    })->name('serial-import.report');
});
