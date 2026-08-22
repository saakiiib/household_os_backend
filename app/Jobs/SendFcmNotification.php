<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends an FCM push notification to a set of users.
 *
 * Decoupled from the web request so the HTTP request does not hold a MySQL
 * connection open while the (slow) FCM HTTP call runs. The in-app DB row is
 * still written synchronously by NotificationService; only the FCM push is
 * queued. Requires a running worker: `php artisan queue:work`.
 */
class SendFcmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public string $type,
        public array $data = [],
        public string $priority = 'normal',
    ) {
        $this->queue = 'notifications';
    }

    public function handle(): void
    {
        if (empty($this->userIds)) {
            return;
        }

        $users = User::whereIn('id', $this->userIds)->get();
        if ($users->isEmpty()) {
            return;
        }

        app(NotificationService::class)->sendFcm(
            $users->all(),
            $this->title,
            $this->body,
            $this->type,
            $this->data,
            $this->priority
        );
    }
}
