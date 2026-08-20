<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\NotificationEngine;
use App\Console\Commands\SendDailyDigest;
use App\Console\Commands\SendHourlyNotification;

class SchedulerController extends Controller
{
    public function run()
    {
        if (request('test') === '1') {
            return $this->sendTestNotification();
        }

        $results = [];

        $engine = app(NotificationEngine::class);

        try {
            $results['task_reminders'] = $engine->runTasks();
        } catch (\Throwable $e) {
            $results['task_reminders'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['renewal_reminders'] = $engine->runRenewals();
        } catch (\Throwable $e) {
            $results['renewal_reminders'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['subscription_check'] = $engine->runSubscription();
        } catch (\Throwable $e) {
            $results['subscription_check'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['daily_digest'] = $this->sendDailyDigest();
        } catch (\Throwable $e) {
            $results['daily_digest'] = 'error: ' . $e->getMessage();
        }

        // Hourly "tasks due in the next hour" notification — currently disabled.
        // Uncomment this block to re-enable it.
        // try {
        //     $results['hourly'] = $this->sendHourly();
        // } catch (\Throwable $e) {
        //     $results['hourly'] = 'error: ' . $e->getMessage();
        // }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    private function sendHourly(): string
    {
        app(SendHourlyNotification::class)->handle();
        return 'Hourly notifications sent';
    }

    private function sendDailyDigest(): string
    {
        $hour = (int) now()->format('H');

        // Only send the daily digest at its 3 scheduled hours (London time),
        // so it still goes out exactly 3x/day even though this route may be
        // called every hour for the hourly notification.
        if (!in_array($hour, [8, 12, 20], true)) {
            return "Digest not scheduled this hour (hour {$hour}) — skipping";
        }

        $cmd = app(SendDailyDigest::class);
        $cmd->handle();
        return 'Digest sent';
    }

    private function sendTestNotification()
    {
        $users = User::whereNotNull('fcm_token')->where('status', 'active')->get();

        if ($users->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active users with FCM tokens']);
        }

        $sent = 0;
        foreach ($users as $user) {
            app(NotificationService::class)->sendToUser(
                $user->id,
                'Test Notification',
                'HouseholdOS notifications are working!',
                'test_notification',
                ['type' => 'test'],
                'normal'
            );
            $sent++;
        }

        return response()->json([
            'success' => true,
            'message' => "Test notification sent to {$sent} user(s)",
        ]);
    }
}
