<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'due_time' => 'date:H:i',
        'completed_at' => 'datetime',
        'parent_task_id' => 'integer',
        'snooze' => 'boolean',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function children()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function getIsRepeatingAttribute(): bool
    {
        return $this->task_type === 'recurring' && $this->frequency !== null;
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date || $this->status === 'completed') return false;

        $now = now();
        $dueDate = $this->due_date->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        // If due date is before today, it's overdue
        if ($dueDate->lt($today)) return true;

        // If due date is today, check the time too
        if ($dueDate->eq($today) && $this->due_time) {
            $dueTime = \Carbon\Carbon::parse($this->due_time);
            $currentTime = $now->copy()->setTime($dueTime->hour, $dueTime->minute);
            return $now->gt($currentTime);
        }

        return false;
    }

    public function getNeedsActionAttribute(): bool
    {
        if (!$this->due_date || $this->status === 'completed') return false;

        $now = now();
        $dueDate = $this->due_date->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        // If due date is before today (overdue), needs action
        if ($dueDate->lt($today)) return true;

        // If due date is today, needs action (check time)
        if ($dueDate->eq($today)) {
            if ($this->due_time) {
                $dueTime = \Carbon\Carbon::parse($this->due_time);
                $currentTime = $now->copy()->setTime($dueTime->hour, $dueTime->minute);
                return $now->gte($currentTime);
            }
            return true; // Due today, no specific time - needs action
        }

        return false;
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) return null;
        $today = now()->startOfDay();
        $due = $this->due_date->startOfDay();
        return (int) $today->diffInDays($due, false);
    }
}
