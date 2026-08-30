<?php

namespace App\Console\Commands;

use App\Models\HouseholdMember;
use App\Models\Notification;
use App\Models\Renewal;
use App\Models\Task;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Send "remind me before" advance reminders via FCM + in-app notifications.
 *
 * For every task/renewal that has a `reminder_before` offset set, we compute the
 * target reminder time (due date/time minus the offset). When that time has
 * arrived (target <= now) we send a push + in-app notification — once per
 * (item, reminder_type), guarded by a dedupe row in the notifications table.
 *
 * This is the backend counterpart to the Flutter local-notification reminders,
 * and is what reliably covers auto-created items and devices where the app was
 * never opened in the reminder window.
 *
 * Schedule: run every minute from a server cron, e.g.
 *   * * * * * cd /path/to/backend && php artisan notifications:reminders >> /dev/null 2>&1
 */
class ReminderCheckCommand extends Command
{
    protected $signature = 'notifications:reminders';
    protected $description = 'Send advance "remind me before" reminders for tasks and renewals';

    private int $sent = 0;

    // Task reminder_before => offset + dedupe type + human label.
    private const TASK_OFFSETS = [
        '15_minutes' => ['label' => '15 minutes', 'type' => 'remind_15_minutes', 'minutes' => 15],
        '1_hour'     => ['label' => '1 hour',     'type' => 'remind_1_hour',     'minutes' => 60],
        '1_day'      => ['label' => '1 day',      'type' => 'remind_1_day',      'minutes' => 1440],
        '3_days'     => ['label' => '3 days',     'type' => 'remind_3_days',     'minutes' => 4320],
        '1_week'     => ['label' => '1 week',     'type' => 'remind_1_week',     'minutes' => 10080],
    ];

    // Renewal reminder_before => offset + dedupe type + human label.
    private const RENEWAL_OFFSETS = [
        '30_days' => ['label' => '30 days', 'type' => 'remind_30_days', 'days' => 30],
        '14_days' => ['label' => '14 days', 'type' => 'remind_14_days', 'days' => 14],
        '7_days'  => ['label' => '7 days',  'type' => 'remind_7_days',  'days' => 7],
        '3_days'  => ['label' => '3 days',  'type' => 'remind_3_days',  'days' => 3],
    ];

    public function handle(): int
    {
        // Overlap lock: skip if a previous run is still executing.
        if (!Cache::add('reminder-check-running', true, 120)) {
            $this->info('Reminder check already running — skipping.');
            return Command::SUCCESS;
        }

        $this->logLine('Run start');
        try {
            $this->checkTaskReminders();
            $this->checkRenewalReminders();
        } finally {
            Cache::forget('reminder-check-running');
        }

        $this->logLine("Run complete. {$this->sent} notification(s) sent.");
        $this->info("Reminder check complete. {$this->sent} notification(s) sent.");
        return Command::SUCCESS;
    }

    private function logLine(string $msg): void
    {
        \Log::info('[ReminderCheck] ' . $msg);
    }

    private function checkTaskReminders(): void
    {
        $now = now();

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereNotNull('reminder_before')
            ->whereIn('reminder_before', array_keys(self::TASK_OFFSETS))
            ->whereDate('due_date', '>=', $now->copy()->subDay())
            ->whereDate('due_date', '<=', $now->copy()->addDays(7))
            ->with('assignedUser:id,first_name,last_name,email,fcm_token', 'createdBy:id,first_name,last_name,email')
            ->select('id', 'title', 'due_date', 'due_time', 'reminder_before', 'assigned_user_id', 'created_by_user_id', 'household_id', 'snooze')
            ->get();

        foreach ($tasks as $task) {
            if (empty($task->assigned_user_id)) {
                continue;
            }

            $cfg = self::TASK_OFFSETS[$task->reminder_before];
            $base = $task->due_date instanceof Carbon
                ? $task->due_date->copy()->startOfDay()
                : Carbon::parse($task->due_date)->startOfDay();

            if ($task->due_time) {
                if ($task->due_time instanceof Carbon) {
                    $base->setTime($task->due_time->hour, $task->due_time->minute, 0);
                } else {
                    $timeStr = (string) $task->due_time;
                    if (preg_match('/(\d{1,2}):(\d{2})/', $timeStr, $m)) {
                        $base->setTime((int) $m[1], (int) $m[2], 0);
                    }
                }
            } else {
                $base->setTime(9, 0, 0);
            }

            $dueStr = $task->due_date instanceof Carbon
                ? $task->due_date->format('M j, Y')
                : Carbon::parse($task->due_date)->format('M j, Y');
            if ($task->due_time) {
                $dueStr .= ' ' . ($task->due_time instanceof Carbon
                    ? $task->due_time->format('g:i A')
                    : Carbon::parse($task->due_time)->format('g:i A'));
            }

            // Base "remind me before" reminder (always sent once).
            $this->sendTaskReminderIfDue(
                $task,
                $cfg['type'],
                $cfg['label'],
                $base->copy()->subMinutes($cfg['minutes']),
                $now,
                $dueStr
            );

            // Snooze cascade: every ladder step that is *closer* to the due time
            // than the chosen base reminder. e.g. base `1_day` => 1_hour + 15_minutes;
            // base `1_week` => 3_days + 1_day + 1_hour + 15_minutes.
            if ($task->snooze) {
                $ladderKeys = array_keys(self::TASK_OFFSETS);
                $baseIndex = array_search($task->reminder_before, $ladderKeys, true);
                if ($baseIndex !== false) {
                    for ($i = 0; $i < $baseIndex; $i++) {
                        $step = self::TASK_OFFSETS[$ladderKeys[$i]];
                        $this->sendTaskReminderIfDue(
                            $task,
                            $step['type'],
                            $step['label'],
                            $base->copy()->subMinutes($step['minutes']),
                            $now,
                            $dueStr
                        );
                    }
                }
            }
        }
    }

    /**
     * Send a single task reminder (FCM + in-app) if its target time has arrived
     * and it hasn't already been sent for this (task, reminder_type).
     */
    private function sendTaskReminderIfDue(Task $task, string $type, string $label, Carbon $target, Carbon $now, string $dueStr): void
    {
        if ($target->gt($now)) {
            return;
        }

        // Key the "already sent" record on a hash of (task, reminder_type, due
        // date, due time). If the task's due date/time is later edited, the hash
        // changes and the reminder is allowed to re-send (otherwise it would be
        // permanently suppressed after the first send).
        $sentKey = md5('task|' . $task->id . '|' . $type . '|' . $task->due_date . '|' . $task->due_time);

        $alreadySent = Notification::where('type', 'task_reminder')
            ->where('data->sent_key', $sentKey)
            ->exists();

        if ($alreadySent) {
            return;
        }

        // Rule 3: Task Creator + Current Assignee.
        $recipientIds = [];
        if (!empty($task->assigned_user_id)) {
            $recipientIds[] = $task->assigned_user_id;
        }
        if (!empty($task->created_by_user_id) && $task->created_by_user_id !== $task->assigned_user_id) {
            $recipientIds[] = $task->created_by_user_id;
        }
        $recipientIds = array_unique($recipientIds);

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
            return;
        }

        app(NotificationService::class)->sendToUsers(
            $verifiedRecipients,
            'Task reminder',
            "'{$task->title}' is due in {$label} on {$dueStr}",
            'task_reminder',
            ['type' => 'task', 'id' => $task->id, 'reminder_type' => $type, 'sent_key' => $sentKey, 'household_id' => $task->household_id],
            'high'
        );
        $this->sent++;
        $this->logLine("SENT TASK #{$task->id} '{$task->title}' -> " . count($verifiedRecipients) . " recipient(s) [{$type}]");
    }

    private function checkRenewalReminders(): void
    {
        $now = now();

        $renewals = Renewal::where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereNotNull('reminder_before')
            ->whereIn('reminder_before', array_keys(self::RENEWAL_OFFSETS))
            ->whereDate('due_date', '>=', $now->copy()->subDay())
            ->whereDate('due_date', '<=', $now->copy()->addDays(31))
            ->select('id', 'title', 'due_date', 'reminder_before', 'household_id', 'created_by_user_id', 'assigned_user_id')
            ->get();

        if ($renewals->isEmpty()) {
            return;
        }

        foreach ($renewals as $renewal) {
            $cfg = self::RENEWAL_OFFSETS[$renewal->reminder_before];
            $base = Carbon::parse($renewal->due_date);
            $target = $base->copy()->subDays($cfg['days']);

            if ($target->gt($now)) {
                continue;
            }

            // Rule 4: Renewal Creator + Current Assignee (NOT all household members).
            $recipientIds = [];
            if ($renewal->created_by_user_id) {
                $recipientIds[] = $renewal->created_by_user_id;
            }
            if ($renewal->assigned_user_id && $renewal->assigned_user_id !== $renewal->created_by_user_id) {
                $recipientIds[] = $renewal->assigned_user_id;
            }
            $recipientIds = array_unique($recipientIds);

            // Rule 8: Verify each recipient still belongs to the household.
            $verifiedRecipients = [];
            foreach ($recipientIds as $userId) {
                $isActive = HouseholdMember::where('household_id', $renewal->household_id)
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

            // Keyed on (renewal, reminder_type, due date) so an edited due date
            // allows the reminder to re-send instead of being permanently skipped.
            $sentKey = md5('renewal|' . $renewal->id . '|' . $cfg['type'] . '|' . $renewal->due_date);

            $alreadySent = Notification::where('type', 'renewal_reminder')
                ->where('data->sent_key', $sentKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $dueStr = $renewal->due_date instanceof Carbon
                ? $renewal->due_date->format('M j, Y')
                : Carbon::parse($renewal->due_date)->format('M j, Y');

            app(NotificationService::class)->sendToUsers(
                $verifiedRecipients,
                'Renewal reminder',
                "'{$renewal->title}' is due in {$cfg['label']} on {$dueStr}",
                'renewal_reminder',
                ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => $cfg['type'], 'sent_key' => $sentKey, 'household_id' => $renewal->household_id],
                'high'
            );
            $this->sent++;
            $this->logLine("SENT RENEWAL #{$renewal->id} '{$renewal->title}' -> " . count($verifiedRecipients) . " recipient(s) [{$cfg['type']}]");
        }
    }
}
