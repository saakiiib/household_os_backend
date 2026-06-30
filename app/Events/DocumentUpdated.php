<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $document;
    public $action;

    /**
     * Create a new event instance.
     */
    public function __construct(Document $document, string $action)
    {
        $this->document = $document;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('household.' . $this->document->household_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'document' => [
                'id' => $this->document->id,
                'title' => $this->document->title,
                'category' => $this->document->category,
            ],
        ];
    }
}
