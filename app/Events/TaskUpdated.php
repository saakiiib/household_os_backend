<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $action;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, string $action)
    {
        $this->task = $task;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('household.' . $this->task->household_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'status' => $this->task->status,
                'assigned_to_user_id' => $this->task->assigned_to_user_id,
            ],
        ];
    }
}
