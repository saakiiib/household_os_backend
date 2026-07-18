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
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'completed';
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) return null;
        return (int) now()->diffInDays($this->due_date, false);
    }
}
