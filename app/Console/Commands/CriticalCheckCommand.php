<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Models\Renewal;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight critical notification check.
 *
 * Replaces the old SchedulerController::run() which loaded ALL tasks/renewals
 * and processed them in PHP. This command uses indexed queries to find only
 * the items that need critical notifications (overdue, due today).
 *
 * Routine reminders (due tomorrow, due in 3 days) are handled by Flutter
 * local notifications on the device.
 */
class CriticalCheckCommand extends Command
{
    protected $signature = 'notifications:critical-check';
    protected $description = 'Send critical notifications: overdue tasks, due-today tasks, overdue renewals, due-today renewals';

    private int $sent = 0;

    // Critical notifications that are not tied to a specific due time are held
    // back until this local hour so they don't all fire at 00:00 (midnight).
    private const MORNING_HOUR = 8;

    public function handle(): int
    {
        // Overlap lock: prevent concurrent runs from multiplying DB queries.
        // If a previous run is still executing, skip this one.
        if (!Cache::add('critical-check-running', true, 60)) {
            $this->info('Critical check already running — skipping.');
            return Command::SUCCESS;
        }

        try {
            $this->checkOverdueTasks();
            $this->checkDueTodayTasks();
            $this->checkOverdueRenewals();
            $this->checkDueTodayRenewals();
            $this->checkDueTodayVehicleServices();
        } finally {
            Cache::forget('critical-check-running');
        }

        $this->info("Critical check complete. {$this->sent} notifications sent.");
        return Command::SUCCESS;
    }

    private function checkOverdueTasks(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereNotNull('assigned_user_id')
            ->whereDate('due_date', '<=', $today)
            ->with('assignedUser:id,first_name,last_name,email,fcm_token')
            ->select('id', 'title', 'due_date', 'due_time', 'assigned_user_id')
            ->get();

        foreach ($tasks as $task) {
            // Only flag as overdue once the actual due date/time has passed.
            if ($this->itemDueDateTime($task->due_date, $task->due_time)->gt($now)) {
                continue;
            }

            // Hold off until the morning hour so it doesn't fire at midnight.
            if ($now->hour < self::MORNING_HOUR) {
                continue;
            }

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
                $this->sent++;
            }
        }
    }

    private function checkDueTodayTasks(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereNotNull('assigned_user_id')
            ->whereDate('due_date', '=', $today)
            ->with('assignedUser:id,first_name,last_name,email,fcm_token')
            ->select('id', 'title', 'due_date', 'due_time', 'assigned_user_id')
            ->get();

        foreach ($tasks as $task) {
            // Skip if the due time has already passed — that's an "overdue"
            // notification now, not a "due today" heads-up.
            if ($this->itemDueDateTime($task->due_date, $task->due_time)->lte($now)) {
                continue;
            }

            // Hold off until the morning hour so it doesn't fire at midnight.
            if ($now->hour < self::MORNING_HOUR) {
                continue;
            }

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
                $this->sent++;
            }
        }
    }

    private function checkOverdueRenewals(): void
    {
        $today = now()->startOfDay();

        // Hold off until the morning hour so it doesn't fire at midnight.
        if (now()->hour < self::MORNING_HOUR) {
            return;
        }

        $renewals = Renewal::where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->select('id', 'title', 'due_date', 'household_id')
            ->get();

        $this->sendRenewalNotifications($renewals, $today, 'overdue', 'critical', function ($renewal) {
            return "'{$renewal->title}' was due {$renewal->due_date->format('d M Y')} — please complete it";
        });
    }

    private function checkDueTodayRenewals(): void
    {
        $today = now()->startOfDay();

        // Hold off until the morning hour so it doesn't fire at midnight.
        if (now()->hour < self::MORNING_HOUR) {
            return;
        }

        $renewals = Renewal::where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $today)
            ->select('id', 'title', 'due_date', 'household_id')
            ->get();

        $this->sendRenewalNotifications($renewals, $today, 'due_today', 'critical', function ($renewal) {
            return "'{$renewal->title}' is due today";
        });
    }

    private function sendRenewalNotifications($renewals, $today, string $reminderType, string $priority, callable $messageFn): void
    {
        if ($renewals->isEmpty()) {
            return;
        }

        $householdIds = $renewals->pluck('household_id')->unique()->all();
        $membersByHousehold = HouseholdMember::whereIn('household_id', $householdIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('household_id');

        foreach ($renewals as $renewal) {
            $memberIds = isset($membersByHousehold[$renewal->household_id])
                ? $membersByHousehold[$renewal->household_id]->pluck('user_id')->all()
                : [];

            if (empty($memberIds)) {
                continue;
            }

            $alreadySent = \App\Models\Notification::where('type', 'renewal_reminder')
                ->where('data->id', $renewal->id)
                ->where('data->reminder_type', $reminderType)
                ->whereDate('created_at', $today)
                ->exists();

            if (!$alreadySent) {
                $title = $reminderType === 'overdue' ? 'Renewal overdue' : 'Renewal due today';
                $body = $messageFn($renewal);

                app(NotificationService::class)->sendToUsers(
                    $memberIds,
                    $title,
                    $body,
                    'renewal_reminder',
                    ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => $reminderType],
                    $priority
                );
                $this->sent++;
            }
        }
    }

    private function checkDueTodayVehicleServices(): void
    {
        $today = now()->startOfDay();

        // Hold off until the morning hour so it doesn't fire at midnight.
        if (now()->hour < self::MORNING_HOUR) {
            return;
        }

        $services = \App\Models\RenewalVehicleService::with('renewal:id,household_id,title')
            ->whereHas('renewal', fn($q) => $q->where('status', 'pending'))
            ->whereDate('service_date', '=', $today)
            ->select('id', 'renewal_id', 'service_type', 'service_date')
            ->get();

        if ($services->isEmpty()) {
            return;
        }

        $householdIds = $services->pluck('renewal.household_id')->filter()->unique()->all();
        $membersByHousehold = HouseholdMember::whereIn('household_id', $householdIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('household_id');

        foreach ($services as $service) {
            $renewal = $service->renewal;
            if (!$renewal) {
                continue;
            }

            $memberIds = isset($membersByHousehold[$renewal->household_id])
                ? $membersByHousehold[$renewal->household_id]->pluck('user_id')->all()
                : [];

            if (empty($memberIds)) {
                continue;
            }

            $alreadySent = \App\Models\Notification::where('type', 'renewal_reminder')
                ->where('data->id', $service->id)
                ->where('data->reminder_type', 'service_due_today')
                ->whereDate('created_at', $today)
                ->exists();

            if (!$alreadySent) {
                $typeLabel = str_replace('_', ' ', $service->service_type);
                app(NotificationService::class)->sendToUsers(
                    $memberIds,
                    ucfirst($typeLabel) . ' due today',
                    "'{$renewal->title}' — {$typeLabel} is due today",
                    'renewal_reminder',
                    ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_due_today', 'service_type' => $service->service_type],
                    'critical'
                );
                $this->sent++;
            }
        }
    }

    /**
     * Build the actual due date/time for an item from its date + optional time.
     * Falls back to 09:00 when no time is set.
     */
    private function itemDueDateTime($date, $time = null): \Carbon\Carbon
    {
        $dt = $date instanceof \Carbon\Carbon ? $date->copy() : \Carbon\Carbon::parse($date);
        $dt->setTime(9, 0, 0);

        if ($time) {
            $t = $time instanceof \Carbon\Carbon ? $time : \Carbon\Carbon::parse($time);
            $dt->setTime($t->hour, $t->minute, 0);
        }

        return $dt;
    }
}
