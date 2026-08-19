<?php

namespace App\Services;

use App\Models\Renewal;
use App\Models\RenewalVehicleService;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\HouseholdMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Unified notification engine.
 *
 * One pass over all modules (tasks, renewals, vehicle services, subscription
 * expiry, daily digest). Every "should we send?" decision is guarded by a
 * deterministic signature so that editing a task/renewal (due date, time or
 * reminder_before) changes the signature and re-triggers the notification
 * correctly — instead of the old "already sent today by type" logic which
 * ignored the actual reminder configuration.
 */
class NotificationEngine
{
    public function __construct(protected NotificationService $notify) {}

    public function runTasks(): string
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

            // Pre-due reminder (only when reminder_before is configured)
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
                    $sig = $this->sig('task', $task->id, 'reminder', [
                        $task->reminder_before,
                        $task->due_date->toDateString(),
                        $task->due_time ?? 'null',
                    ]);
                    if (!$this->sent($sig)) {
                        $this->notify->sendToUser(
                            $task->assigned_user_id,
                            'Task reminder',
                            "'{$task->title}' is due {$timeLabel}",
                            'task_reminder',
                            $this->data('task', $task->id, ['reminder_type' => 'upcoming']),
                            'normal'
                        );
                        $this->mark($sig);
                        $sent++;
                    }
                }
            }

            // Overdue (once per due date)
            if ($diffDays < 0) {
                $sig = $this->sig('task', $task->id, 'overdue', [$task->due_date->toDateString()]);
                if (!$this->sent($sig)) {
                    $this->notify->sendToUser(
                        $task->assigned_user_id,
                        'Task overdue',
                        "'{$task->title}' was due {$task->due_date->format('d M Y')} — please complete it",
                        'task_reminder',
                        $this->data('task', $task->id, ['reminder_type' => 'overdue']),
                        'critical'
                    );
                    $this->mark($sig);
                    $sent++;
                }
            }

            // Due today
            if ($diffDays == 0) {
                $sig = $this->sig('task', $task->id, 'due_today', [$task->due_date->toDateString()]);
                if (!$this->sent($sig)) {
                    $timeLabel = $task->due_time ? 'today at ' . Carbon::parse($task->due_time)->format('g:i A') : 'today';
                    $this->notify->sendToUser(
                        $task->assigned_user_id,
                        'Task due today',
                        "'{$task->title}' is due {$timeLabel}",
                        'task_reminder',
                        $this->data('task', $task->id, ['reminder_type' => 'due_today']),
                        'high'
                    );
                    $this->mark($sig);
                    $sent++;
                }
            }

            // Day before due
            if ($diffDays == 1) {
                $sig = $this->sig('task', $task->id, 'day_before', [$task->due_date->toDateString()]);
                if (!$this->sent($sig)) {
                    $this->notify->sendToUser(
                        $task->assigned_user_id,
                        'Task due tomorrow',
                        "'{$task->title}' is due tomorrow",
                        'task_reminder',
                        $this->data('task', $task->id, ['reminder_type' => 'day_before']),
                        'high'
                    );
                    $this->mark($sig);
                    $sent++;
                }
            }
        }

        return "Task reminders: {$sent} sent";
    }

    public function runRenewals(): string
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

            if (empty($memberIds)) {
                continue;
            }

            $reminderDays = match ($renewal->reminder_before ?? '7_days') {
                '30_days' => 30,
                '14_days' => 14,
                '3_days' => 3,
                default => 7,
            };

            if ($diffDays <= $reminderDays && $diffDays > 1) {
                $sig = $this->sig('renewal', $renewal->id, 'upcoming', [
                    $renewal->reminder_before ?? '7_days',
                    $renewal->due_date->toDateString(),
                ]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            'Renewal reminder',
                            "'{$renewal->title}' is due in {$diffDays} days",
                            'renewal_reminder',
                            $this->data('renewal', $renewal->id, ['reminder_type' => 'upcoming']),
                            'normal'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }

            if ($diffDays == 1) {
                $sig = $this->sig('renewal', $renewal->id, 'day_before', [$renewal->due_date->toDateString()]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            'Renewal due tomorrow',
                            "'{$renewal->title}' is due tomorrow",
                            'renewal_reminder',
                            $this->data('renewal', $renewal->id, ['reminder_type' => 'day_before']),
                            'high'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }

            if ($diffDays == 0) {
                $sig = $this->sig('renewal', $renewal->id, 'due_today', [$renewal->due_date->toDateString()]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            'Renewal due today',
                            "'{$renewal->title}' is due today",
                            'renewal_reminder',
                            $this->data('renewal', $renewal->id, ['reminder_type' => 'due_today']),
                            'critical'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }

            if ($diffDays < 0 && abs($diffDays) % 3 == 0) {
                $sig = $this->sig('renewal', $renewal->id, 'overdue', [$renewal->due_date->toDateString(), abs($diffDays)]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            'Renewal overdue',
                            "'{$renewal->title}' was due {$renewal->due_date->format('d M Y')} — please complete it",
                            'renewal_reminder',
                            $this->data('renewal', $renewal->id, ['reminder_type' => 'overdue']),
                            'critical'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }
        }

        $sent += $this->runVehicleServices($today);

        return "Renewal reminders: {$sent} sent";
    }

    private function runVehicleServices(Carbon $today): int
    {
        $sent = 0;
        $now = now();

        $services = RenewalVehicleService::with('renewal')
            ->whereHas('renewal', fn($q) => $q->where('status', 'pending'))
            ->where('service_date', '>=', $today->copy()->subDays(30))
            ->get();

        foreach ($services as $service) {
            $renewal = $service->renewal;
            if (!$renewal) {
                continue;
            }

            $serviceDate = Carbon::parse($service->service_date)->startOfDay();
            $serviceDt = $serviceDate->copy();
            $diffDays = $today->diffInDays($serviceDt, false);

            $memberIds = HouseholdMember::where('household_id', $renewal->household_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            if (empty($memberIds)) {
                continue;
            }

            $typeLabel = str_replace('_', ' ', $service->service_type);

            if ($diffDays == 0) {
                $sig = $this->sig('service', $service->id, 'due_today', [$service->service_type]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            ucfirst($typeLabel) . ' due today',
                            "'{$renewal->title}' — {$typeLabel} is due today",
                            'renewal_reminder',
                            $this->data('vehicle', $service->id, ['reminder_type' => 'service_due_today', 'service_type' => $service->service_type]),
                            'critical'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }

            if ($diffDays == 1) {
                $sig = $this->sig('service', $service->id, 'day_before', [$service->service_type]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            ucfirst($typeLabel) . ' due tomorrow',
                            "'{$renewal->title}' — {$typeLabel} is due tomorrow",
                            'renewal_reminder',
                            $this->data('vehicle', $service->id, ['reminder_type' => 'service_day_before', 'service_type' => $service->service_type]),
                            'high'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }

            if ($diffDays == 7) {
                $sig = $this->sig('service', $service->id, '7_days', [$service->service_type]);
                if (!$this->sent($sig)) {
                    foreach ($memberIds as $userId) {
                        $this->notify->sendToUser(
                            $userId,
                            ucfirst($typeLabel) . ' in 7 days',
                            "'{$renewal->title}' — {$typeLabel} is due in 7 days",
                            'renewal_reminder',
                            $this->data('vehicle', $service->id, ['reminder_type' => 'service_7_days', 'service_type' => $service->service_type]),
                            'normal'
                        );
                    }
                    $this->mark($sig);
                    $sent++;
                }
            }
        }

        return $sent;
    }

    public function runSubscription(): string
    {
        $now = now();
        $handled = 0;

        // Active -> grace period (past period end, not yet expired)
        $toGrace = Subscription::where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->get();

        foreach ($toGrace as $sub) {
            $sub->moveToGracePeriod();
            $handled++;
        }

        // Grace -> expired
        $toExpired = Subscription::where('status', 'grace_period')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        foreach ($toExpired as $sub) {
            $sub->markExpired();
            $handled++;
        }

        // Renewal warnings at 7/3/1 days
        foreach ([7, 3, 1] as $days) {
            $targetDate = $now->copy()->addDays($days);
            $subs = Subscription::where('status', 'active')
                ->whereNotNull('current_period_end')
                ->whereDate('current_period_end', $targetDate->toDateString())
                ->with(['user', 'plan'])
                ->get();

            foreach ($subs as $sub) {
                $sig = $this->sig('subscription', $sub->id, "renewal_{$days}d", []);
                if ($this->sent($sig)) {
                    continue;
                }
                $userId = $sub->user?->id;
                if (!$userId) {
                    continue;
                }
                $this->notify->sendToUser(
                    $userId,
                    'Subscription renewal',
                    "Your {$sub->plan?->name} subscription renews in {$days} day" . ($days > 1 ? 's' : ''),
                    'subscription_reminder',
                    $this->data('subscription', $sub->id, ['reminder_type' => 'renewal']),
                    'high'
                );
                $this->mark($sig);
            }
        }

        // Grace period warnings (next 3 days)
        $graceWarnings = Subscription::where('status', 'grace_period')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays(3))
            ->with(['user', 'plan'])
            ->get();

        foreach ($graceWarnings as $sub) {
            $daysLeft = (int) $now->diffInDays($sub->expires_at);
            if ($daysLeft <= 0) {
                continue;
            }
            $sig = $this->sig('subscription', $sub->id, "grace_{$daysLeft}d", []);
            if ($this->sent($sig)) {
                continue;
            }
            $userId = $sub->user?->id;
            if (!$userId) {
                continue;
            }
            $this->notify->sendToUser(
                $userId,
                'Subscription expiring',
                "Your subscription expires in {$daysLeft} day" . ($daysLeft > 1 ? 's' : ''),
                'subscription_reminder',
                $this->data('subscription', $sub->id, ['reminder_type' => 'grace_period']),
                'critical'
            );
            $this->mark($sig);
        }

        return "Subscription: {$handled} transitions, warnings sent";
    }

    /**
     * Build a stable signature for a notification event. Any change to the
     * inputs (e.g. editing reminder_before / due date) produces a new
     * signature, so the engine re-sends instead of skipping.
     */
    private function sig(string $module, int $id, string $event, array $parts): string
    {
        return 'ntf:' . $module . ':' . $id . ':' . $event . ':' . md5(implode('|', $parts));
    }

    private function data(string $module, int $id, array $extra = []): array
    {
        return array_merge([
            'module' => $module,
            'action_type' => $module,
            'action_id' => $id,
            'type' => $module,
            'id' => $id,
        ], $extra);
    }

    private function sent(string $signature): bool
    {
        return Cache::has($signature);
    }

    private function mark(string $signature, int $ttlSeconds = 172800): void
    {
        Cache::put($signature, true, $ttlSeconds);
    }
}
