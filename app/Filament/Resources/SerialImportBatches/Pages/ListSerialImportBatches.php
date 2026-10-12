<?php

namespace App\Filament\Resources\SerialImportBatches\Pages;

use App\Filament\Resources\SerialImportBatches\SerialImportBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListSerialImportBatches extends ListRecords
{
    protected static string $resource = SerialImportBatchResource::class;

    protected static ?string $title = 'تاریخچه ایمپورت سریال';
}
