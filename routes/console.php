<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:sync-prices')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/tokeniko-sync.log'));

Schedule::command('priceboard:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/priceboard-sync.log'));
