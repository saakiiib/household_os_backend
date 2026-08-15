<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Document;
use App\Models\Renewal;
use App\Models\HouseholdMember;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'notifications:send-reminders';
    protected $description = 'Check due dates and send reminder notifications for tasks, documents, and renewals';

    public function handle(): int
    {
        $this->sendTaskReminders();
        $this->sendDocumentReminders();
        $this->sendRenewalReminders();

        $this->info('Reminders sent successfully.');
        return 0;
    }

    private function sendTaskReminders(): void
    {
        $now = now();

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereNotNull('reminder_before')
            ->whereNotNull('assigned_user_id')
            ->get();

        foreach ($tasks as $task) {
            $dueDate = $task->due_date->copy()->startOfDay();
            $today = $now->copy()->startOfDay();
            $diffDays = $today->diffInDays($dueDate, false);

            $shouldRemind = false;
            $timeLabel = '';

            switch ($task->reminder_before) {
                case '15_minutes':
                    if ($task->due_time) {
                        $dueTime = \Carbon\Carbon::parse($task->due_time);
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
                // Check if we already sent a reminder today
                $alreadySent = \App\Models\Notification::where('user_id', $task->assigned_user_id)
                    ->where('type', 'task_reminder')
                    ->where('data->id', $task->id)
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$alreadySent) {
                    app(NotificationService::class)->sendToUser(
                        $task->assigned_user_id,
                        'Task reminder',
                        "'{$task->title}' is due {$timeLabel}",
                        'task_reminder',
                        ['type' => 'task', 'id' => $task->id]
                    );
                }
            }
        }
    }

    private function sendDocumentReminders(): void
    {
        $now = now();

        $documents = Document::whereNotNull('due_date')
            ->whereNotNull('reminder_before')
            ->whereNotNull('created_by_user_id')
            ->get();

        foreach ($documents as $doc) {
            $dueDate = $doc->due_date->copy()->startOfDay();
            $today = $now->copy()->startOfDay();
            $diffDays = $today->diffInDays($dueDate, false);

            $shouldRemind = false;
            $timeLabel = '';

            switch ($doc->reminder_before) {
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
                case '30_days':
                    $shouldRemind = $diffDays <= 30 && $diffDays >= 0;
                    $timeLabel = 'in 30 days';
                    break;
            }

            if ($shouldRemind) {
                $alreadySent = \App\Models\Notification::where('user_id', $doc->created_by_user_id)
                    ->where('type', 'document_reminder')
                    ->where('data->id', $doc->id)
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$alreadySent) {
                    app(NotificationService::class)->sendToUser(
                        $doc->created_by_user_id,
                        'Document reminder',
                        "'{$doc->title}' — action needed {$timeLabel}",
                        'document_reminder',
                        ['type' => 'document', 'id' => $doc->id]
                    );
                }
            }
        }
    }

    private function sendRenewalReminders(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();

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

            // Respect the reminder_before setting
            $reminderDays = match($renewal->reminder_before ?? '7_days', '30_days' => 30, '14_days' => 14, '3_days' => 3, default => 7);

            // Pre-due reminder: send once when within the reminder window
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'upcoming']
                        );
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'day_before']
                        );
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'due_today']
                        );
                    }
                }
            }

            // Overdue escalation: remind every 3 days after due
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'overdue']
                        );
                    }
                }
            }
        }

        // Vehicle service date reminders
        $this->sendVehicleServiceReminders();
    }

    private function sendVehicleServiceReminders(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        $services = \App\Models\RenewalVehicleService::with('renewal')
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

            // Due today
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_due_today', 'service_type' => $service->service_type]
                        );
                    }
                }
            }

            // Day before
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_day_before', 'service_type' => $service->service_type]
                        );
                    }
                }
            }

            // 7 days before
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
                            ['type' => 'renewal', 'id' => $renewal->id, 'reminder_type' => 'service_7_days', 'service_type' => $service->service_type]
                        );
                    }
                }
            }
        }
    }
}
