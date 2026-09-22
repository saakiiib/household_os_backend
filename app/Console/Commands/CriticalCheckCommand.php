<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Models\Renewal;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\DeviceToken;
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
            \Log::info('[CriticalCheck] Run skipped — already running.');
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

        \Log::info("[CriticalCheck] Run complete. {$this->sent} notification(s) sent.");
        $this->info("Critical check complete. {$this->sent} notifications sent.");
        return Command::SUCCESS;
    }

    private function checkOverdueTasks(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $now->copy()->addDay()->toDateString())
            ->with('assignedUser:id,first_name,last_name,email,fcm_token', 'createdBy:id,first_name,last_name,email')
            ->select('id', 'title', 'due_date', 'due_time', 'created_by_user_id', 'assigned_user_id', 'household_id')
            ->get();

        foreach ($tasks as $task) {
            $recipientIds = [];

            // Personal reminder rule: assigned -> assignee only; unassigned -> creator.
            $targetUserId = !empty($task->assigned_user_id) ? $task->assigned_user_id : $task->created_by_user_id;
            if (!empty($targetUserId)) {
                $localNow = $this->localNowForUser((int) $targetUserId);
                $localDue = $this->itemDueDateTime($task->due_date, $task->due_time, $localNow->getTimezone()->getName());
                if ($localDue->gt($localNow) || $localNow->hour < self::MORNING_HOUR) {
                    continue;
                }

                $isActive = HouseholdMember::where('household_id', $task->household_id)
                    ->where('user_id', $targetUserId)
                    ->where('status', 'active')
                    ->exists();
                if ($isActive) {
                    $recipientIds[] = $targetUserId;
                }
            }

            $recipientIds = array_unique($recipientIds);

            if (empty($recipientIds)) {
                continue;
            }

            $sentKey = md5('task-critical|' . $task->id . '|overdue|' . $task->due_date->format('Y-m-d') . '|' . ($task->due_time ?? ''));
            $alreadySent = \App\Models\Notification::where('type', 'task_reminder')
                ->where('data->sent_key', $sentKey)
                ->whereIn('user_id', $recipientIds)
                ->exists();

            if (!$alreadySent) {
                app(NotificationService::class)->persistToUsers(
                    $recipientIds,
                    'Task overdue',
                    "'{$task->title}' was due {$task->due_date->format('d M Y')} — please complete it",
                    'task_reminder',
                    ['type' => 'task', 'id' => $task->id, 'reminder_type' => 'overdue', 'sent_key' => $sentKey, 'household_id' => $task->household_id],
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
            ->whereBetween('due_date', [$now->copy()->subDay()->toDateString(), $now->copy()->addDay()->toDateString()])
            ->with('assignedUser:id,first_name,last_name,email,fcm_token', 'createdBy:id,first_name,last_name,email')
            ->select('id', 'title', 'due_date', 'due_time', 'assigned_user_id', 'created_by_user_id', 'household_id')
            ->get();

        foreach ($tasks as $task) {
            // Personal reminder rule: assigned -> assignee only; unassigned -> creator.
            $recipientIds = [];
            $targetUserId = !empty($task->assigned_user_id) ? $task->assigned_user_id : $task->created_by_user_id;
            if (!empty($targetUserId)) {
                $localNow = $this->localNowForUser((int) $targetUserId);
                $localDue = $this->itemDueDateTime($task->due_date, $task->due_time, $localNow->getTimezone()->getName());
                if (!$localDue->isSameDay($localNow) || $localDue->lte($localNow) || $localNow->hour < self::MORNING_HOUR) {
                    continue;
                }
                $recipientIds[] = $targetUserId;
            }

            // Rule 8: Verify each recipient still belongs to the household.
            $verifiedRecipients = [];
            foreach ($recipientIds as $userId) {
                $isActive = HouseholdMember::where('household_id', $task->household_id)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->exists();
                if ($isActive) {
                    $verifiedRecipients[] = $userId;
                }
            }

            if (empty($verifiedRecipients)) {
                continue;
            }

            $sentKey = md5('task-critical|' . $task->id . '|due_today|' . $task->due_date->format('Y-m-d') . '|' . ($task->due_time ?? ''));
            $alreadySent = \App\Models\Notification::where('type', 'task_reminder')
                ->where('data->sent_key', $sentKey)
                ->whereIn('user_id', $verifiedRecipients)
                ->exists();

            if (!$alreadySent) {
                $timeLabel = $task->due_time ? 'today at ' . \Carbon\Carbon::parse($task->due_time)->format('g:i A') : 'today';
                app(NotificationService::class)->persistToUsers(
                    $verifiedRecipients,
                    'Task due today',
                    "'{$task->title}' is due {$timeLabel}",
                    'task_reminder',
                    ['type' => 'task', 'id' => $task->id, 'reminder_type' => 'due_today', 'sent_key' => $sentKey, 'household_id' => $task->household_id],
                    'high'
                );
                $this->sent++;
            }
        }
    }

    private function checkOverdueRenewals(): void
    {
        $today = now()->startOfDay();

        $renewals = Renewal::where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->addDay()->toDateString())
            ->select('id', 'title', 'due_date', 'household_id', 'created_by_user_id', 'assigned_user_id')
            ->get();

        $this->sendRenewalNotifications($renewals, $today, 'overdue', 'critical', function ($renewal) {
            return "'{$renewal->title}' was due {$renewal->due_date->format('d M Y')} — please complete it";
        });
    }

    private function checkDueTodayRenewals(): void
    {
        $today = now()->startOfDay();

        $renewals = Renewal::where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->subDay()->toDateString(), now()->addDay()->toDateString()])
            ->select('id', 'title', 'due_date', 'household_id', 'created_by_user_id', 'assigned_user_id')
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

        foreach ($renewals as $renewal) {
            // Personal reminder rule: assigned -> assignee only; unassigned -> creator.
            $recipientIds = [];
            $targetUserId = !empty($renewal->assigned_user_id) ? $renewal->assigned_user_id : $renewal->created_by_user_id;
            if (!empty($targetUserId)) {
                $localNow = $this->localNowForUser((int) $targetUserId);
                $dueLocal = \Carbon\Carbon::parse($renewal->due_date->format('Y-m-d'), $localNow->getTimezone()->getName());
                $isDueToday = $dueLocal->isSameDay($localNow);
                $isOverdue = $dueLocal->lt($localNow->copy()->startOfDay());
                if ($localNow->hour < self::MORNING_HOUR ||
                    ($reminderType === 'due_today' && !$isDueToday) ||
                    ($reminderType === 'overdue' && !$isOverdue)) {
                    continue;
                }

                $isActive = HouseholdMember::where('household_id', $renewal->household_id)
                    ->where('user_id', $targetUserId)
                    ->where('status', 'active')
                    ->exists();
                if ($isActive) {
                    $recipientIds[] = $targetUserId;
                }
            }

            if (empty($recipientIds)) {
                continue;
            }

            $sentKey = md5('renewal-critical|' . $renewal->id . '|' . $reminderType . '|' . $renewal->due_date->format('Y-m-d'));
            $alreadySent = \App\Models\Notification::where('type', 'renewal_reminder')
                ->where('data->sent_key', $sentKey)
                ->whereIn('user_id', $recipientIds)
                ->exists();

            if (!$alreadySent) {
                $title = $reminderType === 'overdue' ? 'Renewal overdue' : 'Renewal due today';
                $body = $messageFn($renewal);

                app(NotificationService::class)->persistToUsers(
                    $recipientIds,
                    $title,
                    $body,
                    'renewal_reminder',
                    ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => $reminderType, 'sent_key' => $sentKey, 'household_id' => $renewal->household_id],
                    $priority
                );
                $this->sent++;
            }
        }
    }

    private function checkDueTodayVehicleServices(): void
    {
        $today = now()->startOfDay();

        // Include creator/assignee IDs: recipient routing below depends on
        // them. Loading only id/household/title made every vehicle-service
        // notification have an empty recipient list and therefore never send.
        $services = \App\Models\RenewalVehicleService::with('renewal:id,household_id,title,created_by_user_id,assigned_user_id')
            ->whereHas('renewal', fn($q) => $q->where('status', 'pending'))
            ->whereBetween('service_date', [now()->subDay()->toDateString(), now()->addDay()->toDateString()])
            ->select('id', 'renewal_id', 'service_type', 'service_date')
            ->get();

        if ($services->isEmpty()) {
            return;
        }

        foreach ($services as $service) {
            $renewal = $service->renewal;
            if (!$renewal) {
                continue;
            }

            // Personal reminder rule: assigned -> assignee only; unassigned -> creator.
            $recipientIds = [];
            $targetUserId = !empty($renewal->assigned_user_id) ? $renewal->assigned_user_id : $renewal->created_by_user_id;
            if (!empty($targetUserId)) {
                $localNow = $this->localNowForUser((int) $targetUserId);
                $serviceLocal = \Carbon\Carbon::parse($service->service_date->format('Y-m-d'), $localNow->getTimezone()->getName());
                if (!$serviceLocal->isSameDay($localNow) || $localNow->hour < self::MORNING_HOUR) {
                    continue;
                }

                $isActive = HouseholdMember::where('household_id', $renewal->household_id)
                    ->where('user_id', $targetUserId)
                    ->where('status', 'active')
                    ->exists();
                if ($isActive) {
                    $recipientIds[] = $targetUserId;
                }
            }

            if (empty($recipientIds)) {
                continue;
            }

            $sentKey = md5('vehicle-service|' . $service->id . '|' . $service->service_type . '|' . $service->service_date->format('Y-m-d'));
            $alreadySent = \App\Models\Notification::where('type', 'renewal_reminder')
                ->where('data->sent_key', $sentKey)
                ->whereIn('user_id', $recipientIds)
                ->exists();

            if (!$alreadySent) {
                $typeLabel = str_replace('_', ' ', $service->service_type);
                app(NotificationService::class)->persistToUsers(
                    $recipientIds,
                    ucfirst($typeLabel) . ' due today',
                    "'{$renewal->title}' — {$typeLabel} is due today",
                    'renewal_reminder',
                    ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_due_today', 'service_type' => $service->service_type, 'sent_key' => $sentKey, 'household_id' => $renewal->household_id],
                    'critical'
                );
                $this->sent++;
            }
        }
    }

    /** Recipient-local clock based on the most recently registered device timezone. */
    private function localNowForUser(int $userId): \Carbon\Carbon
    {
        $timezone = DeviceToken::where('user_id', $userId)
            ->whereNotNull('timezone')
            ->latest('updated_at')
            ->value('timezone') ?: config('app.timezone', 'UTC');

        try {
            return now($timezone);
        } catch (\Throwable $e) {
            return now(config('app.timezone', 'UTC'));
        }
    }

    /**
     * Build the actual due date/time for an item from its date + optional time.
     * Falls back to 09:00 when no time is set.
     */
    private function itemDueDateTime($date, $time = null, ?string $timezone = null): \Carbon\Carbon
    {
        $timezone = $timezone ?: config('app.timezone', 'UTC');
        $dateString = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : \Carbon\Carbon::parse($date)->format('Y-m-d');
        $dt = \Carbon\Carbon::parse($dateString, $timezone);
        $dt->setTime(9, 0, 0);

        if ($time) {
            $t = $time instanceof \Carbon\Carbon ? $time : \Carbon\Carbon::parse($time);
            $dt->setTime($t->hour, $t->minute, 0);
        }

        return $dt;
    }
}
