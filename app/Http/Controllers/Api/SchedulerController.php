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
        $now = now('Europe/London');
        \Illuminate\Support\Facades\Log::info("SCHEDULER: Cron triggered at {$now->format('Y-m-d H:i:s')} London time.");

        if (request('test') === '1') {
            return $this->sendTestNotification();
        }

        $results = [];

        $engine = app(NotificationEngine::class);

        try {
            $results['task_reminders'] = $engine->runTasks();
        } catch (\Throwable $e) {
            $results['task_reminders'] = 'error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error("SCHEDULER: Task reminders error: " . $e->getMessage());
        }

        try {
            $results['renewal_reminders'] = $engine->runRenewals();
        } catch (\Throwable $e) {
            $results['renewal_reminders'] = 'error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error("SCHEDULER: Renewal reminders error: " . $e->getMessage());
        }

        try {
            $results['subscription_check'] = $engine->runSubscription();
        } catch (\Throwable $e) {
            $results['subscription_check'] = 'error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error("SCHEDULER: Subscription check error: " . $e->getMessage());
        }

        try {
            $results['daily_digest'] = $this->sendDailyDigest();
        } catch (\Throwable $e) {
            $results['daily_digest'] = 'error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error("SCHEDULER: Daily digest error: " . $e->getMessage());
        }

        \Illuminate\Support\Facades\Log::info("SCHEDULER: Completed", ['results' => $results]);

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
        $now = now('Europe/London');
        $hour = (int) $now->format('H');

        \Illuminate\Support\Facades\Log::info("DIGEST: Checking schedule. London hour: {$hour}");

        // Send daily digest at scheduled hours: 8 AM, 12 PM, 5 PM, 8 PM
        if (!in_array($hour, [8, 12, 17, 20], true)) {
            $msg = "Digest not scheduled this hour (hour {$hour}) — skipping";
            \Illuminate\Support\Facades\Log::info("DIGEST: {$msg}");
            return $msg;
        }

        \Illuminate\Support\Facades\Log::info("DIGEST: Triggering digest command for hour {$hour}");
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
