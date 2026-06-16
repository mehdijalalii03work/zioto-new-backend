<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:sync-prices')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/tokeniko-sync.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
