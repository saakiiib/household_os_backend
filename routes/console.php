<?php

use Illuminate\Support\Facades\Schedule;

// Advance "remind me before" reminders for tasks and renewals
Schedule::command('notifications:reminders')->everyMinute();

// Critical notifications: overdue/due-today tasks and renewals
Schedule::command('notifications:critical-check')->everyMinute();

// Subscription expiry + grace period transitions
Schedule::command('subscription:check-expiry')->hourly();
