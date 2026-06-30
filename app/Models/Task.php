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
        'completed_at' => 'datetime',
        'estimated_hours' => 'float',
        'reward_points' => 'integer',
    ];

    /**
     * Get the household this task belongs to.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user who created the task.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user assigned to this task.
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the user who completed the task.
     */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * Get the completion history for this task.
     */
    public function completions()
    {
        return $this->hasMany(TaskCompletion::class);
    }

    /**
     * Check if this task is recurring.
     */
    public function isRecurring(): bool
    {
        return in_array($this->task_type, ['recurring', 'rotating']);
    }
}
