<?php

namespace App\Events;

use App\Models\Renewal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RenewalUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $renewal;
    public $action;

    /**
     * Create a new event instance.
     */
    public function __construct(Renewal $renewal, string $action)
    {
        $this->renewal = $renewal;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('household.' . $this->renewal->household_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'renewal' => [
                'id' => $this->renewal->id,
                'title' => $this->renewal->title,
                'status' => $this->renewal->status,
                'renewal_date' => $this->renewal->renewal_date->toDateString(),
            ],
        ];
    }
}
