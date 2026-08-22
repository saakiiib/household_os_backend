<?php

use Illuminate\Support\Facades\Schedule;

// Critical notifications: overdue/due-today tasks and renewals
Schedule::command('notifications:critical-check')->everyMinute();

// Subscription expiry + grace period transitions
Schedule::command('subscription:check-expiry')->hourly();
