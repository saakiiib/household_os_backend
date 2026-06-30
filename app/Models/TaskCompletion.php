<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskCompletion extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the task this completion belongs to.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who completed the task.
     */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
