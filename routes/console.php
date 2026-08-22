<?php

use Illuminate\Support\Facades\Schedule;

// Process FCM notification queue — runs via scheduler so no separate cron needed.
Schedule::command('queue:work database --queue=notifications --stop-when-empty')->everyMinute();

// Critical notifications: overdue/due-today tasks and renewals — every minute
Schedule::command('notifications:critical-check')->everyMinute();

// Subscription expiry + grace period transitions — every hour
Schedule::command('subscription:check-expiry')->hourly();
