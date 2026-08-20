<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Task;
use App\Models\Renewal;
use App\Models\Document;
use App\Models\HouseholdMember;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendDailyDigest extends Command
{
    protected $signature = 'notifications:send-daily-digest {--period=}';
    protected $description = 'Send daily digest notifications: morning (8am), midday (12pm), afternoon (5pm), evening (8pm)';

    public function handle(): int
    {
        $period = $this->option('period');

        if (!$period) {
            $hour = (int) now('Europe/London')->format('H');
            if ($hour < 12) {
                $period = 'morning';
            } elseif ($hour < 16) {
                $period = 'midday';
            } elseif ($hour < 19) {
                $period = 'afternoon';
            } else {
                $period = 'evening';
            }
        }

        $this->info("DIGEST: Starting {$period} digest run.");

        $users = User::whereNotNull('fcm_token')
            ->where('status', 'active')
            ->get();

        $this->info("DIGEST: Found " . $users->count() . " active user(s) with non-null FCM token.");

        $sent = 0;

        foreach ($users as $user) {
            $dayKey = 'digest:' . $period . ':' . $user->id . ':' . now('Europe/London')->toDateString();
            if (Cache::has($dayKey)) {
                $this->info("DIGEST: User {$user->id} ({$user->email}) already received {$period} digest today — skipping.");
                continue;
            }

            $this->info("DIGEST: Processing {$period} digest for user {$user->id} ({$user->email})...");

            $memberHouseholdIds = HouseholdMember::where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('household_id')
                ->all();

            if (empty($memberHouseholdIds)) {
                $this->info("DIGEST: User {$user->id} has no active household. Sending default greeting.");
                $this->sendNoHouseholdMessage($user, $period);
                Cache::put($dayKey, true, now('Europe/London')->endOfDay());
                $sent++;
                continue;
            }

            $this->info("DIGEST: User {$user->id} has " . count($memberHouseholdIds) . " household(s). Building digest...");

            $message = match ($period) {
                'morning' => $this->buildMorningDigest($user, $memberHouseholdIds),
                'midday' => $this->buildMiddayDigest($user, $memberHouseholdIds),
                'afternoon' => $this->buildAfternoonDigest($user, $memberHouseholdIds),
                'evening' => $this->buildEveningDigest($user, $memberHouseholdIds),
            };

            app(NotificationService::class)->sendToUser(
                $user->id,
                $message['title'],
                $message['body'],
                "daily_digest_{$period}",
                [
                    'module' => 'digest',
                    'action_type' => 'digest',
                    'action_id' => null,
                    'period' => $period,
                    'tasks_count' => $message['tasks_count'] ?? 0,
                    'renewals_count' => $message['renewals_count'] ?? 0,
                    'overdue_count' => $message['overdue_count'] ?? 0,
                    'completed_count' => $message['completed_count'] ?? 0,
                ],
                'low'
            );

            Cache::put($dayKey, true, now('Europe/London')->endOfDay());
            $sent++;
        }

        $this->info("DIGEST: {$sent} {$period} digest notification(s) sent.");
        return 0;
    }

    private function sendNoHouseholdMessage(User $user, string $period): void
    {
        $name = $user->first_name ?: 'there';

        $greetings = [
            'morning' => [
                'title' => "Good morning, {$name}! ☀️",
                'body' => "Welcome to your day! Create or join a household to start managing tasks, renewals, and documents. Your organized life awaits!",
            ],
            'midday' => [
                'title' => "Midday check-in, {$name} 👋",
                'body' => "No household yet? Join one or create your own to get the most out of HouseholdOS. We're here when you're ready!",
            ],
            'afternoon' => [
                'title' => "Afternoon check-in, {$name} 🌤️",
                'body' => "Hope your afternoon is going well! Join or create a household to get organized.",
            ],
            'evening' => [
                'title' => "Good evening, {$name} 🌙",
                'body' => "Winding down for the day? Set up your household tomorrow and start fresh. Sleep well!",
            ],
        ];

        $msg = $greetings[$period];

        app(NotificationService::class)->sendToUser(
            $user->id,
            $msg['title'],
            $msg['body'],
            "daily_digest_{$period}",
            ['period' => $period],
            'low'
        );
    }

    private function buildMorningDigest(User $user, array $householdIds): array
    {
        $name = $user->first_name ?: 'there';
        $today = now()->startOfDay();

        $todayTasks = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today)
            ->count();

        $overdueTasks = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $todayRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today)
            ->count();

        $overdueRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $upcomingTasks = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>', $today)
            ->whereDate('due_date', '<=', $today->copy()->addDays(3))
            ->count();

        $upcomingRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>', $today)
            ->whereDate('due_date', '<=', $today->copy()->addDays(7))
            ->count();

        $totalDueToday = $todayTasks + $todayRenewals;
        $totalOverdue = $overdueTasks + $overdueRenewals;
        $totalUpcoming = $upcomingTasks + $upcomingRenewals;

        $dayOfWeek = now()->format('l');
        $greeting = $this->getTimeGreeting($dayOfWeek);

        if ($totalDueToday == 0 && $totalOverdue == 0 && $totalUpcoming == 0) {
            return [
                'title' => "{$greeting}, {$name}! ✨",
                'body' => "You're all caught up! No tasks or renewals on your plate today. Enjoy your day — you've earned it!",
                'tasks_count' => 0,
                'renewals_count' => 0,
                'overdue_count' => 0,
            ];
        }

        $parts = [];

        if ($totalDueToday > 0) {
            $items = [];
            if ($todayTasks > 0) {
                $items[] = $this->pluralize($todayTasks, 'task');
            }
            if ($todayRenewals > 0) {
                $items[] = $this->pluralize($todayRenewals, 'renewal');
            }
            $parts[] = "📋 " . implode(' and ', $items) . " due today";
        }

        if ($totalOverdue > 0) {
            $overdueItems = [];
            if ($overdueTasks > 0) {
                $overdueItems[] = $this->pluralize($overdueTasks, 'task');
            }
            if ($overdueRenewals > 0) {
                $overdueItems[] = $this->pluralize($overdueRenewals, 'renewal');
            }
            $parts[] = "⚠️ " . implode(' and ', $overdueItems) . " overdue — needs attention";
        }

        if ($totalUpcoming > 0) {
            $upItems = [];
            if ($upcomingTasks > 0) {
                $upItems[] = $this->pluralize($upcomingTasks, 'task');
            }
            if ($upcomingRenewals > 0) {
                $upItems[] = $this->pluralize($upcomingRenewals, 'renewal');
            }
            $parts[] = "📅 " . implode(' and ', $upItems) . " coming up this week";
        }

        $body = implode('. ', $parts) . '.';

        if ($totalOverdue > 0) {
            $body .= ' Let\'s tackle those overdue items first!';
        } elseif ($totalDueToday > 3) {
            $body .= ' Busy day ahead — stay focused, you got this! 💪';
        } else {
            $body .= ' Have a productive day!';
        }

        return [
            'title' => "{$greeting}, {$name}! ☀️",
            'body' => $body,
            'tasks_count' => $todayTasks + $overdueTasks + $upcomingTasks,
            'renewals_count' => $todayRenewals + $overdueRenewals + $upcomingRenewals,
            'overdue_count' => $totalOverdue,
        ];
    }

    private function buildMiddayDigest(User $user, array $householdIds): array
    {
        $name = $user->first_name ?: 'there';
        $today = now()->startOfDay();

        $completedToday = Task::whereIn('household_id', $householdIds)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', $today)
            ->count();

        $pendingToday = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today)
            ->count();

        $overdueTasks = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $pendingRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today)
            ->count();

        $overdueRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $totalRemaining = $pendingToday + $pendingRenewals;
        $totalOverdue = $overdueTasks + $overdueRenewals;

        if ($completedToday == 0 && $totalRemaining == 0 && $totalOverdue == 0) {
            return [
                'title' => "Midday check-in, {$name} 👋",
                'body' => "Quiet afternoon ahead — no pending tasks or renewals. Take a well-deserved break or get ahead on something new!",
                'tasks_count' => 0,
                'renewals_count' => 0,
                'overdue_count' => 0,
                'completed_count' => 0,
            ];
        }

        $parts = [];

        if ($completedToday > 0) {
            $parts[] = "✅ {$completedToday} " . $this->pluralize($completedToday, 'task') . " completed — great progress!";
        }

        if ($totalRemaining > 0) {
            $items = [];
            if ($pendingToday > 0) {
                $items[] = $this->pluralize($pendingToday, 'task');
            }
            if ($pendingRenewals > 0) {
                $items[] = $this->pluralize($pendingRenewals, 'renewal');
            }
            $parts[] = "📋 " . implode(' and ', $items) . " still to go today";
        }

        if ($totalOverdue > 0) {
            $parts[] = "⚠️ {$totalOverdue} overdue item" . ($totalOverdue > 1 ? 's' : '') . " — don't forget these";
        }

        $body = implode('. ', $parts) . '.';

        if ($completedToday > 0 && $totalRemaining == 0 && $totalOverdue == 0) {
            $body = "🎉 {$completedToday} " . $this->pluralize($completedToday, 'task') . " done! You're all clear for the day. Keep it up!";
        } elseif ($completedToday > 3) {
            $body .= ' You\'re on fire today! 🔥';
        } elseif ($totalOverdue > 0) {
            $body .= ' Push through the remaining items!';
        } else {
            $body .= ' Halfway through — keep the momentum going!';
        }

        return [
            'title' => "Midday check-in, {$name} 💪",
            'body' => $body,
            'tasks_count' => $pendingToday + $overdueTasks,
            'renewals_count' => $pendingRenewals + $overdueRenewals,
            'overdue_count' => $totalOverdue,
            'completed_count' => $completedToday,
        ];
    }

    private function buildAfternoonDigest(User $user, array $householdIds): array
    {
        $res = $this->buildMiddayDigest($user, $householdIds);
        $name = $user->first_name ?: 'there';
        $res['title'] = "Afternoon update, {$name} 🌤️";
        return $res;
    }

    private function buildEveningDigest(User $user, array $householdIds): array
    {
        $name = $user->first_name ?: 'there';
        $today = now()->startOfDay();

        $completedToday = Task::whereIn('household_id', $householdIds)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', $today)
            ->count();

        $pendingToday = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today)
            ->count();

        $overdueTasks = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $pendingRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today)
            ->count();

        $overdueRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $totalRemaining = $pendingToday + $pendingRenewals;
        $totalOverdue = $overdueTasks + $overdueRenewals;

        $tomorrow = $today->copy()->addDay();
        $tomorrowTasks = Task::whereIn('household_id', $householdIds)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $tomorrow)
            ->count();

        $tomorrowRenewals = Renewal::whereIn('household_id', $householdIds)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $tomorrow)
            ->count();

        $tomorrowCount = $tomorrowTasks + $tomorrowRenewals;

        if ($completedToday == 0 && $totalRemaining == 0 && $totalOverdue == 0) {
            return [
                'title' => "Good evening, {$name} 🌙",
                'body' => "Quiet day today — nothing on your list. " . ($tomorrowCount > 0
                    ? "Tomorrow you have {$tomorrowCount} item" . ($tomorrowCount > 1 ? 's' : '') . " lined up. Rest well!"
                    : "No worries tomorrow either. Enjoy your evening!"),
                'tasks_count' => 0,
                'renewals_count' => 0,
                'overdue_count' => 0,
                'completed_count' => 0,
            ];
        }

        $parts = [];

        if ($completedToday > 0) {
            $parts[] = "✅ You completed {$completedToday} " . $this->pluralize($completedToday, 'task') . " today";
        }

        if ($totalRemaining > 0) {
            $items = [];
            if ($pendingToday > 0) {
                $items[] = $this->pluralize($pendingToday, 'task');
            }
            if ($pendingRenewals > 0) {
                $items[] = $this->pluralize($pendingRenewals, 'renewal');
            }
            $parts[] = "📋 " . implode(' and ', $items) . " still pending";
        }

        if ($totalOverdue > 0) {
            $parts[] = "⚠️ {$totalOverdue} overdue item" . ($totalOverdue > 1 ? 's' : '') . " — consider tackling these first thing tomorrow";
        }

        $body = implode('. ', $parts) . '.';

        if ($completedToday > 0 && $totalRemaining == 0 && $totalOverdue == 0) {
            $body = "🎉 {$completedToday} " . $this->pluralize($completedToday, 'task') . " done today! Everything's wrapped up. Have a great evening!";
        } elseif ($completedToday > $totalRemaining) {
            $body .= ' More done than left — strong finish! 💪';
        } else {
            $body .= ' Wrap up what you can and rest easy!';
        }

        if ($tomorrowCount > 0) {
            $body .= " 📅 Tomorrow: {$tomorrowCount} item" . ($tomorrowCount > 1 ? 's' : '') . " lined up.";
        } else {
            $body .= ' 📅 Light day tomorrow — enjoy!';
        }

        return [
            'title' => "Day wrap-up, {$name} 🌙",
            'body' => $body,
            'tasks_count' => $pendingToday + $overdueTasks,
            'renewals_count' => $pendingRenewals + $overdueRenewals,
            'overdue_count' => $totalOverdue,
            'completed_count' => $completedToday,
        ];
    }

    private function getTimeGreeting(string $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            'Monday' => 'Happy Monday',
            'Tuesday' => 'Happy Tuesday',
            'Wednesday' => 'Happy Wednesday',
            'Thursday' => 'Happy Thursday',
            'Friday' => 'Happy Friday',
            'Saturday' => 'Happy Saturday',
            'Sunday' => 'Happy Sunday',
            default => 'Good morning',
        };
    }

    private function pluralize(int $count, string $word): string
    {
        return $count == 1 ? $word : $word . 's';
    }
}
