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
        // Prevent overlapping cron runs. If a previous run is still in progress
        // (e.g. stuck on a slow Firebase call) we skip this tick instead of
        // stacking more DB connections — that pile-up is what freezes MySQL.
        $lock = \Illuminate\Support\Facades\Cache::lock('scheduler:cron:run', 300);
        if (!$lock->get()) {
            \Illuminate\Support\Facades\Log::info('SCHEDULER: Another cron run is still in progress — skipping to avoid DB overload.');
            return response()->json(['success' => true, 'skipped' => 'locked']);
        }

        try {
            $now = now('Europe/London');
            \Illuminate\Support\Facades\Log::info("SCHEDULER: Cron route triggered at {$now->format('Y-m-d H:i:s')} London time.");

            if (request('test') === '1') {
                return $this->sendTestNotification();
            }

            $results = [];

            $engine = app(NotificationEngine::class);

            try {
                $results['task_reminders'] = $engine->runTasks();
            } catch (\Throwable $e) {
                $results['task_reminders'] = 'error: ' . $e->getMessage();
                \Illuminate\Support\Facades\Log::error("SCHEDULER: Error running task reminders: " . $e->getMessage());
            }

            try {
                $results['renewal_reminders'] = $engine->runRenewals();
            } catch (\Throwable $e) {
                $results['renewal_reminders'] = 'error: ' . $e->getMessage();
                \Illuminate\Support\Facades\Log::error("SCHEDULER: Error running renewal reminders: " . $e->getMessage());
            }

            try {
                $results['subscription_check'] = $engine->runSubscription();
            } catch (\Throwable $e) {
                $results['subscription_check'] = 'error: ' . $e->getMessage();
                \Illuminate\Support\Facades\Log::error("SCHEDULER: Error running subscription checks: " . $e->getMessage());
            }

            try {
                $results['daily_digest'] = $this->sendDailyDigest();
            } catch (\Throwable $e) {
                $results['daily_digest'] = 'error: ' . $e->getMessage();
                \Illuminate\Support\Facades\Log::error("SCHEDULER: Error running daily digest: " . $e->getMessage());
            }

            \Illuminate\Support\Facades\Log::info("SCHEDULER: Completed cron run", ['results' => $results]);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } finally {
            $lock->release();
        }
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

        // Allow manual testing via ?force=1 or ?digest=morning
        $isForced = request('force') == '1' || request('digest') !== null;

        \Illuminate\Support\Facades\Log::info("SCHEDULER: Checking daily digest schedule. Current London hour: {$hour}, forced: " . ($isForced ? 'yes' : 'no'));

        if (!$isForced) {
            // Send daily digest at scheduled hours: 8 AM (morning), 12 PM (midday), 5 PM (afternoon), 9 PM (evening)
            if (!in_array($hour, [8, 12, 17, 21], true)) {
                $msg = "Digest not scheduled this hour (hour {$hour}) — skipping";
                \Illuminate\Support\Facades\Log::info("SCHEDULER: {$msg}");
                return $msg;
            }
        }

        // Determine the period explicitly and run the command directly
        // (same pattern as sendHourly). Using Artisan::call() from HTTP
        // context doesn't properly bootstrap the command, causing
        // "Call to a member function getOption() on null".
        $period = request('digest') ?: match (true) {
            $hour < 12  => 'morning',
            $hour < 16  => 'midday',
            $hour < 19  => 'afternoon',
            default     => 'evening',
        };

        \Illuminate\Support\Facades\Log::info("SCHEDULER: Triggering digest for period: {$period}");

        // The command is invoked directly (not through Artisan), so its
        // console output is null. Bind a NullOutput so any $this->info()/
        // $this->line() call inside the command cannot throw
        // "Call to a member function writeln() on null".
        $command = app(SendDailyDigest::class);
        $command->setOutput(new \Symfony\Component\Console\Output\NullOutput());
        $command->setForced($isForced);
        $command->setPeriod($period);
        $command->handle();

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
