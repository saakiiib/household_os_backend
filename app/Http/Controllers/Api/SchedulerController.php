<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use App\Models\Renewal;
use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\RenewalVehicleService;
use App\Models\User;
use App\Services\NotificationService;
use App\Notifications\SubscriptionExpiryNotification;
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

        try {
            $results['task_reminders'] = $this->sendTaskReminders();
        } catch (\Throwable $e) {
            $results['task_reminders'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['renewal_reminders'] = $this->sendRenewalReminders();
        } catch (\Throwable $e) {
            $results['renewal_reminders'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['subscription_check'] = $this->checkSubscriptionExpiry();
        } catch (\Throwable $e) {
            $results['subscription_check'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['daily_digest'] = $this->sendDailyDigest();
        } catch (\Throwable $e) {
            $results['daily_digest'] = 'error: ' . $e->getMessage();
        }

        try {
            $results['hourly'] = $this->sendHourly();
        } catch (\Throwable $e) {
            $results['hourly'] = 'error: ' . $e->getMessage();
        }

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

    private function sendTaskReminders(): int
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $sent = 0;

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereNotNull('assigned_user_id')
            ->get();

        foreach ($tasks as $task) {
            $dueDate = $task->due_date->copy()->startOfDay();
            $diffDays = $today->diffInDays($dueDate, false);

            // Pre-due reminders (only if reminder_before is set)
            if ($task->reminder_before) {
                $shouldRemind = false;
                $timeLabel = '';

                switch ($task->reminder_before) {
                    case '15_minutes':
                        if ($task->due_time) {
                            $reminderTime = $dueDate->copy()->subMinutes(15);
                            $shouldRemind = $now->gte($reminderTime) && $now->lt($dueDate->copy()->addMinutes(15));
                            $timeLabel = 'in 15 minutes';
                        }
                        break;
                    case '1_hour':
                        $reminderTime = $dueDate->copy()->subHour();
                        $shouldRemind = $now->gte($reminderTime) && $now->lt($dueDate->copy()->addHour());
                        $timeLabel = 'in 1 hour';
                        break;
                    case '1_day':
                        $shouldRemind = $diffDays <= 1 && $diffDays >= 0;
                        $timeLabel = 'tomorrow';
                        break;
                    case '3_days':
                        $shouldRemind = $diffDays <= 3 && $diffDays >= 0;
                        $timeLabel = 'in 3 days';
                        break;
                    case '1_week':
                        $shouldRemind = $diffDays <= 7 && $diffDays >= 0;
                        $timeLabel = 'in 1 week';
                        break;
                }

                if ($shouldRemind) {
                    $alreadySent = \App\Models\Notification::where('user_id', $task->assigned_user_id)
                        ->where('type', 'task_reminder')
                        ->where('data->id', $task->id)
                        ->where('data->reminder_type', 'upcoming')
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $task->assigned_user_id,
                            'Task reminder',
                            "'{$task->title}' is due {$timeLabel}",
                            'task_reminder',
                            ['type' => 'task', 'id' => $task->id, 'reminder_type' => 'upcoming'],
                            'normal'
                        );
                        $sent++;
                    }
                }
            }

            // Overdue notification — for ALL incomplete tasks past due date
            if ($diffDays < 0) {
                $alreadySent = \App\Models\Notification::where('user_id', $task->assigned_user_id)
                    ->where('type', 'task_reminder')
                    ->where('data->id', $task->id)
                    ->where('data->reminder_type', 'overdue')
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$alreadySent) {
                    app(NotificationService::class)->sendToUser(
                        $task->assigned_user_id,
                        'Task overdue',
                        "'{$task->title}' was due {$task->due_date->format('d M Y')} — please complete it",
                        'task_reminder',
                        ['type' => 'task', 'id' => $task->id, 'reminder_type' => 'overdue'],
                        'critical'
                    );
                    $sent++;
                }
            }

            // Due today notification
            if ($diffDays == 0) {
                $alreadySent = \App\Models\Notification::where('user_id', $task->assigned_user_id)
                    ->where('type', 'task_reminder')
                    ->where('data->id', $task->id)
                    ->where('data->reminder_type', 'due_today')
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$alreadySent) {
                    $timeLabel = $task->due_time ? 'today at ' . \Carbon\Carbon::parse($task->due_time)->format('g:i A') : 'today';
                    app(NotificationService::class)->sendToUser(
                        $task->assigned_user_id,
                        'Task due today',
                        "'{$task->title}' is due {$timeLabel}",
                        'task_reminder',
                        ['type' => 'task', 'id' => $task->id, 'reminder_type' => 'due_today'],
                        'high'
                    );
                    $sent++;
                }
            }

            // Day before due notification (always sent, regardless of reminder_before)
            if ($diffDays == 1) {
                $alreadySent = \App\Models\Notification::where('user_id', $task->assigned_user_id)
                    ->where('type', 'task_reminder')
                    ->where('data->id', $task->id)
                    ->where('data->reminder_type', 'day_before')
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$alreadySent) {
                    app(NotificationService::class)->sendToUser(
                        $task->assigned_user_id,
                        'Task due tomorrow',
                        "'{$task->title}' is due tomorrow",
                        'task_reminder',
                        ['type' => 'task', 'id' => $task->id, 'reminder_type' => 'day_before'],
                        'high'
                    );
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function sendRenewalReminders(): int
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $sent = 0;

        $renewals = Renewal::where('status', 'pending')
            ->whereNotNull('due_date')
            ->get();

        foreach ($renewals as $renewal) {
            $dueDate = $renewal->due_date->copy()->startOfDay();
            $diffDays = $today->diffInDays($dueDate, false);

            $memberIds = HouseholdMember::where('household_id', $renewal->household_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            if (empty($memberIds)) continue;

            $reminderDays = match($renewal->reminder_before ?? '7_days') {
                '30_days' => 30,
                '14_days' => 14,
                '3_days' => 3,
                default => 7,
            };

            // Pre-due reminder
            if ($diffDays <= $reminderDays && $diffDays > 1) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'upcoming')
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            'Renewal reminder',
                            "'{$renewal->title}' is due in {$diffDays} days",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'upcoming'],
                            'normal'
                        );
                        $sent++;
                    }
                }
            }

            // Day before due
            if ($diffDays == 1) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'day_before')
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            'Renewal due tomorrow',
                            "'{$renewal->title}' is due tomorrow",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'day_before'],
                            'high'
                        );
                        $sent++;
                    }
                }
            }

            // Due today
            if ($diffDays == 0) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'due_today')
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            'Renewal due today',
                            "'{$renewal->title}' is due today",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'due_today'],
                            'critical'
                        );
                        $sent++;
                    }
                }
            }

            // Overdue escalation
            if ($diffDays < 0 && abs($diffDays) % 3 == 0) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'overdue')
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            'Renewal overdue',
                            "'{$renewal->title}' was due {$renewal->due_date->format('d M Y')} — please complete it",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'overdue'],
                            'critical'
                        );
                        $sent++;
                    }
                }
            }
        }

        // Vehicle service date reminders
        $sent += $this->sendVehicleServiceReminders();

        return $sent;
    }

    private function sendVehicleServiceReminders(): int
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $sent = 0;

        $services = RenewalVehicleService::with('renewal')
            ->whereHas('renewal', function ($q) {
                $q->where('status', 'pending');
            })
            ->where('service_date', '>=', $today->copy()->subDays(30))
            ->get();

        foreach ($services as $service) {
            $renewal = $service->renewal;
            if (!$renewal) continue;

            $serviceDate = \Carbon\Carbon::parse($service->service_date)->startOfDay();
            $diffDays = $today->diffInDays($serviceDate, false);

            $memberIds = HouseholdMember::where('household_id', $renewal->household_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            if (empty($memberIds)) continue;

            $typeLabel = str_replace('_', ' ', $service->service_type);

            if ($diffDays == 0) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'service_due_today')
                        ->where('data->service_type', $service->service_type)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            ucfirst($typeLabel) . ' due today',
                            "'{$renewal->title}' — {$typeLabel} is due today",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_due_today', 'service_type' => $service->service_type],
                            'critical'
                        );
                        $sent++;
                    }
                }
            }

            if ($diffDays == 1) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'service_day_before')
                        ->where('data->service_type', $service->service_type)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            ucfirst($typeLabel) . ' due tomorrow',
                            "'{$renewal->title}' — {$typeLabel} is due tomorrow",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_day_before', 'service_type' => $service->service_type],
                            'high'
                        );
                        $sent++;
                    }
                }
            }

            if ($diffDays == 7) {
                foreach ($memberIds as $userId) {
                    $alreadySent = \App\Models\Notification::where('user_id', $userId)
                        ->where('type', 'renewal_reminder')
                        ->where('data->id', $renewal->id)
                        ->where('data->reminder_type', 'service_7_days')
                        ->where('data->service_type', $service->service_type)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (!$alreadySent) {
                        app(NotificationService::class)->sendToUser(
                            $userId,
                            ucfirst($typeLabel) . ' in 7 days',
                            "'{$renewal->title}' — {$typeLabel} is due in 7 days",
                            'renewal_reminder',
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_7_days', 'service_type' => $service->service_type],
                            'normal'
                        );
                        $sent++;
                    }
                }
            }
        }

        return $sent;
    }

    private function checkSubscriptionExpiry(): string
    {
        $now = now();
        $handled = 0;

        // Active subscriptions past period_end but not yet past expires_at → grace period
        $toGrace = Subscription::where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get();

        foreach ($toGrace as $sub) {
            $sub->moveToGracePeriod();
            $handled++;
        }

        // Grace period subscriptions past expires_at → expired
        $toExpired = Subscription::where('status', 'grace_period')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        foreach ($toExpired as $sub) {
            $sub->markExpired();
            $handled++;
        }

        // Send expiry warnings at 7, 3, 1 days before renewal
        foreach ([7, 3, 1] as $days) {
            $targetDate = $now->copy()->addDays($days);
            $key = "renewal_{$days}d";

            $subs = Subscription::where('status', 'active')
                ->whereNotNull('current_period_end')
                ->whereDate('current_period_end', $targetDate->toDateString())
                ->with(['user', 'plan'])
                ->get();

            foreach ($subs as $sub) {
                $meta = $sub->metadata ?? [];
                if (!isset($meta["notified_{$key}"])) {
                    $sub->user?->notify(new SubscriptionExpiryNotification(
                        $sub,
                        "Your {$sub->plan?->name} subscription renews in {$days} day" . ($days > 1 ? 's' : ''),
                        'renewal'
                    ));
                    $meta["notified_{$key}"] = now()->toIso8601String();
                    $sub->update(['metadata' => $meta]);
                }
            }
        }

        // Grace period warnings
        $graceWarning = Subscription::where('status', 'grace_period')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays(3))
            ->with(['user', 'plan'])
            ->get();

        foreach ($graceWarning as $sub) {
            $daysLeft = (int) $now->diffInDays($sub->expires_at);
            if ($daysLeft <= 0) continue;

            $key = "grace_{$daysLeft}d";
            $meta = $sub->metadata ?? [];
            if (!isset($meta["notified_{$key}"])) {
                $sub->user?->notify(new SubscriptionExpiryNotification(
                    $sub,
                    "Your subscription expires in {$daysLeft} day" . ($daysLeft > 1 ? 's' : ''),
                    'grace_period'
                ));
                $meta["notified_{$key}"] = now()->toIso8601String();
                $sub->update(['metadata' => $meta]);
            }
        }

        return "Handled {$handled} subscription transitions";
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
