<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send monthly expense summaries on the 1st of each month at 8 AM
Schedule::command('emails:monthly-summary')->monthlyOn(1, '08:00');
