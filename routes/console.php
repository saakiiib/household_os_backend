<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send reminders daily at 8:00 AM
Schedule::command('notifications:send-reminders')->dailyAt('08:00');

// Check subscription expiry twice daily
Schedule::command('subscription:check-expiry')->twiceDaily(8, 20);
