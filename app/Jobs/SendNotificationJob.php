<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public string $type,
        public array $data = [],
        public string $priority = 'normal',
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $service): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        $service->sendToUser(
            $this->userId,
            $this->title,
            $this->body,
            $this->type,
            $this->data,
            $this->priority,
            $user
        );
    }
}
