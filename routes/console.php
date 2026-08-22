<?php

use Illuminate\Support\Facades\Schedule;

// Critical notifications: overdue/due-today tasks and renewals — every minute
Schedule::command('notifications:critical-check')->everyMinute();

// Subscription expiry + grace period transitions — every hour
Schedule::command('subscription:check-expiry')->hourly();
