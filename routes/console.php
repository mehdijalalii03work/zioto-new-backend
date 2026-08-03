<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('priceboard:sync')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/priceboard-sync.log'));

Schedule::command('orders:cancel-unpaid')
    ->everyMinute()
    ->withoutOverlapping(1);

Schedule::command('pulse:check')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('backup:run')
    ->dailyAt('03:00')
    ->withoutOverlapping();
