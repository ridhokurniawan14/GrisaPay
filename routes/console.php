<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinkron data master dari GrisaID: tiap jam pada jam sekolah, plus sekali dini hari.
Schedule::command('grisapay:sync-grisaid --trigger=scheduled')
    ->hourly()
    ->between('06:00', '20:00')
    ->withoutOverlapping(15)
    ->runInBackground();

Schedule::command('grisapay:sync-grisaid --trigger=scheduled')
    ->dailyAt('01:30')
    ->withoutOverlapping(15)
    ->runInBackground();

Schedule::command('grisapay:backup --label=auto --keep=14')->dailyAt('02:00')->withoutOverlapping(30);
