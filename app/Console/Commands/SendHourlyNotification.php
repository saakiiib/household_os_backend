<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Task;
use App\Models\HouseholdMember;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendHourlyNotification extends Command
{
    protected $signature = 'notifications:send-hourly';
    protected $description = 'Send an hourly notification about tasks due in the next hour (only tasks assigned to the user)';

    public function handle(): int
    {
        $now = now();
        $inOneHour = $now->copy()->addHour();

        $users = User::whereNotNull('fcm_token')
            ->where('status', 'active')
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $householdIds = HouseholdMember::where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('household_id')
                ->all();

            if (empty($householdIds)) {
                $this->sendMessage(
                    $user,
                    'Hourly check-in',
                    'No household yet — nothing to track. Create or join one to get started!'
                );
                $sent++;
                continue;
            }

            // Only tasks assigned to THIS user, not completed, with a due date
            $tasks = Task::whereIn('household_id', $householdIds)
                ->where('assigned_user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->get();

            // Tasks due within the next hour (only those that have a due time)
            $dueNextHour = $tasks->filter(function (Task $task) use ($now, $inOneHour) {
                if (!$task->due_time) {
                    return false;
                }
                $due = $this->dueDateTime($task);
                return $due && $due->gte($now) && $due->lte($inOneHour);
            });

            if ($dueNextHour->isNotEmpty()) {
                $titles = $dueNextHour->take(5)->map(function (Task $t) {
                    $time = $t->due_time ? ' (' . Carbon::parse($t->due_time)->format('g:i A') . ')' : '';
                    return '• ' . $t->title . $time;
                })->implode("\n");

                $more = $dueNextHour->count() > 5 ? "\n…and " . ($dueNextHour->count() - 5) . ' more' : '';

                $this->sendMessage(
                    $user,
                    'Due in the next hour',
                    "You have {$dueNextHour->count()} task(s) due soon:\n{$titles}{$more}",
                    'hourly_upcoming',
                    ['count' => $dueNextHour->count()]
                );
                $sent++;
                continue;
            }

            // Nothing in the next hour — report the rest of today / overdue
            $dueToday = $tasks->filter(fn(Task $t) => $t->due_date && $t->due_date->isToday());
            $overdue = $tasks->filter(fn(Task $t) => $t->due_date && $t->due_date->lt(now()->startOfDay()));

            if ($dueToday->isNotEmpty()) {
                $this->sendMessage(
                    $user,
                    'Hourly check-in',
                    "No tasks due in the next hour, but you have {$dueToday->count()} task(s) left for today.",
                    'hourly_none',
                    ['count' => $dueToday->count()]
                );
            } elseif ($overdue->isNotEmpty()) {
                $this->sendMessage(
                    $user,
                    'Hourly check-in',
                    "No tasks due in the next hour, but you have {$overdue->count()} overdue task(s) to catch up on.",
                    'hourly_none',
                    ['count' => $overdue->count()]
                );
            } else {
                $this->sendMessage(
                    $user,
                    'Hourly check-in',
                    'No tasks due — enjoy your hour!',
                    'hourly_none',
                    ['count' => 0]
                );
            }
            $sent++;
        }

        $this->info("{$sent} hourly notifications sent.");
        return 0;
    }

    private function dueDateTime(Task $task): ?Carbon
    {
        if (!$task->due_date) {
            return null;
        }
        $dt = $task->due_date->copy()->startOfDay();
        if ($task->due_time) {
            $t = Carbon::parse($task->due_time);
            $dt->setTime($t->hour, $t->minute);
        }
        return $dt;
    }

    private function sendMessage(User $user, string $title, string $body, string $type = 'hourly', array $data = []): void
    {
        app(NotificationService::class)->sendToUser(
            $user->id,
            $title,
            $body,
            $type,
            array_merge(['kind' => 'hourly'], $data),
            'normal'
        );
    }
}
