<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;

class SchedulerController extends Controller
{
    /**
     * Lightweight cron entry point.
     *
     * Protected by CRON_SECRET token. Only runs critical notification
     * checks — routine reminders are handled by Flutter local notifications.
     */
    public function run()
    {
        // Secret token guard — prevents random people from triggering cron
        $secret = config('services.cron_secret', env('CRON_SECRET'));
        if ($secret && request('token') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $now = now('Europe/London');
        \Illuminate\Support\Facades\Log::info("SCHEDULER: Cron triggered at {$now->format('Y-m-d H:i:s')} London time.");

        if (request('test') === '1') {
            return $this->sendTestNotification();
        }

        $results = [];

        // Critical task/renewal check — indexed queries only
        try {
            $results['critical_check'] = \Artisan::call('notifications:critical-check');
            $results['critical_check_output'] = \Artisan::output();
        } catch (\Throwable $e) {
            $results['critical_check'] = 'error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error("SCHEDULER: Critical check error: " . $e->getMessage());
        }

        // Subscription transitions + expiry warnings
        try {
            $results['subscription_check'] = \Artisan::call('subscription:check-expiry');
            $results['subscription_check_output'] = \Artisan::output();
        } catch (\Throwable $e) {
            $results['subscription_check'] = 'error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error("SCHEDULER: Subscription check error: " . $e->getMessage());
        }

        \Illuminate\Support\Facades\Log::info("SCHEDULER: Completed", ['results' => $results]);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
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
