<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('social:ai:monitor-releases')
    ->hourly()
    ->withoutOverlapping(55);

Schedule::command('operations:monitor-queues')
    ->everyFiveMinutes()
    ->withoutOverlapping(4);

Schedule::command('operations:heartbeat')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->when(fn (): bool => is_string(config('operations.health_token')) && strlen(config('operations.health_token')) >= 32);

Schedule::command('inventory:remind-overdue-counts')
    ->dailyAt('09:00')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping(55);
