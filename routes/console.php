<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('priceboard:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/priceboard-sync.log'));
