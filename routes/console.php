<?php

use Illuminate\Support\Facades\Schedule;

// Advance "remind me before" reminders for tasks and renewals
Schedule::command('notifications:reminders')->everyMinute();

// Critical notifications: overdue/due-today tasks and renewals
Schedule::command('notifications:critical-check')->everyMinute();

// Subscription expiry + grace period transitions
Schedule::command('subscription:check-expiry')->hourly();

// Auto-create next occurrence for recurring tasks and renewals
// (skips missed days so an overdue series never backfills a backlog).
Schedule::command('recurring:generate')->everyFiveMinutes();
