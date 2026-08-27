<?php

namespace App\Services;

use App\Models\Renewal;
use App\Models\Task;
use Carbon\Carbon;

/**
 * Auto-generates the next occurrence for recurring tasks and renewals.
 *
 * Design goals (per product requirement):
 *  - Generation is driven by the scheduler (cron), not by ad-hoc endpoints, so a
 *    recurring series keeps producing occurrences even if a user never opens the
 *    app or never marks an item complete.
 *  - Only ONE future occurrence is ever created at a time. Missed occurrences are
 *    skipped by stepping the due date forward until it reaches today, so an
 *    overdue series (e.g. 4 days late) produces a single upcoming task — never a
 *    backlog of 4.
 *  - A new occurrence is only created when the latest instance in a series is
 *    completed or overdue, and only if that series does not already have a
 *    pending occurrence. This makes the generator idempotent and safe to run
 *    frequently.
 */
class RecurringGenerator
{
    public static function runAll(): array
    {
        return [
            'tasks'    => self::generateTasks(),
            'renewals' => self::generateRenewals(),
        ];
    }

    private static function generateTasks(): int
    {
        $all = Task::query()
            ->whereNotNull('due_date')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('task_type', 'recurring')->whereNotNull('frequency');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('repeat')->where('repeat', '!=', 'does_not_repeat');
                });
            })
            ->get();

        return self::generateForCollection($all, 'parent_task_id', function ($latest) {
            return self::generateForTask($latest);
        });
    }

    private static function generateRenewals(): int
    {
        $all = Renewal::query()
            ->whereNotNull('due_date')
            ->whereNotNull('frequency')
            ->get();

        return self::generateForCollection($all, 'parent_renewal_id', function ($latest) {
            return self::generateForRenewal($latest);
        });
    }

    /**
     * Given the full set of recurring items, find the latest item of each chain
     * (the one whose id is not referenced as a parent by another item in the
     * set) and, if it is completed/overdue, generate its next occurrence.
     */
    private static function generateForCollection($items, string $parentKey, callable $generator): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $parentIds = $items
            ->whereNotNull($parentKey)
            ->pluck($parentKey)
            ->unique()
            ->all();

        $latestOnes = $items->whereNotIn('id', $parentIds);

        $count = 0;
        foreach ($latestOnes as $latest) {
            if (!self::isDueForNext($latest->status, $latest->due_date)) {
                continue;
            }
            if ($generator($latest)) {
                $count++;
            }
        }

        return $count;
    }

    private static function isDueForNext(?string $status, $dueDate): bool
    {
        if ($status === 'completed') {
            return true;
        }

        if ($dueDate === null) {
            return false;
        }

        $due = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);

        return $due->startOfDay()->lt(now()->startOfDay());
    }

    public static function generateForTask(Task $latest): ?Task
    {
        // Idempotency: don't create if this series already has a pending child.
        if ($latest->children()->where('status', 'pending')->exists()) {
            return null;
        }

        $frequency = self::taskFrequency($latest);
        if ($frequency === null) {
            return null;
        }

        $next = self::nextOccurrence($latest->due_date, $frequency);
        if ($next === null) {
            return null;
        }

        return Task::create([
            'household_id'       => $latest->household_id,
            'created_by_user_id' => $latest->created_by_user_id,
            'assigned_user_id'   => $latest->assigned_user_id,
            'parent_task_id'     => $latest->id,
            'title'              => $latest->title,
            'description'        => $latest->description,
            'task_type'          => $latest->task_type,
            'priority'           => $latest->priority,
            'frequency'          => $latest->frequency,
            'due_date'           => $next->format('Y-m-d'),
            'due_time'           => $latest->due_time,
            'reminder_before'    => $latest->reminder_before,
            'snooze'             => $latest->snooze,
            'repeat'             => $latest->repeat,
            'notes'              => $latest->notes,
            'status'             => 'pending',
        ]);
    }

    public static function generateForRenewal(Renewal $latest): ?Renewal
    {
        if ($latest->children()->where('status', 'pending')->exists()) {
            return null;
        }

        if (empty($latest->frequency)) {
            return null;
        }

        $next = self::nextOccurrence($latest->due_date, $latest->frequency);
        if ($next === null) {
            return null;
        }

        $new = Renewal::create([
            'household_id'       => $latest->household_id,
            'created_by_user_id' => $latest->created_by_user_id,
            'assigned_user_id'   => $latest->assigned_user_id,
            'parent_renewal_id'  => $latest->id,
            'renewal_type'       => $latest->renewal_type,
            'vehicle_id'         => $latest->vehicle_id,
            'title'              => $latest->title,
            'category'           => $latest->category,
            'frequency'          => $latest->frequency,
            'due_date'           => $next->format('Y-m-d'),
            'amount'             => $latest->amount,
            'reminder_before'    => $latest->reminder_before,
            'notes'              => $latest->notes,
            'status'             => 'pending',
        ]);

        if ($latest->renewal_type === 'vehicle') {
            $latest->load('vehicleServices');
            foreach ($latest->vehicleServices as $service) {
                $new->vehicleServices()->create([
                    'service_type'   => $service->service_type,
                    'service_date'   => $service->service_date,
                    'service_amount' => $service->service_amount,
                ]);
            }
        }

        return $new;
    }

    private static function taskFrequency(Task $task): ?string
    {
        if ($task->task_type === 'recurring' && $task->frequency) {
            return $task->frequency;
        }

        if ($task->repeat && $task->repeat !== 'does_not_repeat') {
            return $task->repeat;
        }

        return null;
    }

    /**
     * Compute the next occurrence after $from, skipping any missed steps so the
     * result is always today or later (never in the past).
     */
    private static function nextOccurrence($from, string $frequency): ?Carbon
    {
        if ($from === null) {
            return null;
        }

        $date = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from)->startOfDay();
        $today = now()->startOfDay();

        $date = self::addStep($date, $frequency);
        while ($date->lt($today)) {
            $date = self::addStep($date, $frequency);
        }

        return $date;
    }

    private static function addStep(Carbon $date, string $frequency): Carbon
    {
        switch ($frequency) {
            case 'daily':
                return $date->copy()->addDay();
            case 'weekly':
                return $date->copy()->addDays(7);
            case 'biweekly':
                return $date->copy()->addDays(14);
            case 'monthly':
                return $date->copy()->addMonthNoOverflow();
            case 'quarterly':
                return $date->copy()->addMonths(3);
            case 'yearly':
            case 'annual':
                return $date->copy()->addYear();
            default:
                // Unknown frequency: fall back to a single day step.
                return $date->copy()->addDay();
        }
    }
}
