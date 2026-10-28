<?php

namespace App\Filament\Resources\AgencyRequests\Pages;

use App\Filament\Resources\AgencyRequests\AgencyRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListAgencyRequests extends ListRecords
{
    protected static string $resource = AgencyRequestResource::class;

    protected static ?string $title = 'لیست درخواست‌های نمایندگی';
}
