<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(protected Messaging $messaging) {}

    public function sendToUser(int $userId, string $title, string $body, string $type, array $data = [], string $priority = 'normal', ?User $user = null): void
    {
        $user = $user ?? User::find($userId);
        if (!$user) return;

        $this->saveToDb($userId, $title, $body, $type, $data, $priority);
        $this->sendFcm([$user], $title, $body, $type, $data, $priority);
    }

    public function sendToUsers(array $userIds, string $title, string $body, string $type, array $data = [], string $priority = 'normal'): void
    {
        $users = User::whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            $this->saveToDb($user->id, $title, $body, $type, $data, $priority);
        }
        $this->sendFcm($users->all(), $title, $body, $type, $data, $priority);
    }

    private function saveToDb(int $userId, string $title, string $body, string $type, array $data, string $priority): void
    {
        Notification::create([
            'user_id'  => $userId,
            'title'    => $title,
            'body'     => $body,
            'type'     => $type,
            'priority' => in_array($priority, Notification::PRIORITIES) ? $priority : 'normal',
            'data'     => $data,
        ]);
    }

    public function sendFcm(array $users, string $title, string $body, string $type, array $data, string $priority = 'normal'): void
    {
        $tokens = collect($users)
            ->filter(fn($u) => !empty($u->fcm_token))
            ->pluck('fcm_token')
            ->values()
            ->all();

        if (empty($tokens)) {
            Log::warning('[NotificationService] No FCM tokens found for users: ' . collect($users)->pluck('id')->implode(','));
            return;
        }

        Log::info('[NotificationService] Sending FCM to ' . count($tokens) . ' token(s): ' . $type);

        $payload = array_merge($data, ['type' => $type, 'priority' => $priority]);

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $message = CloudMessage::new()
                    ->withNotification(FcmNotification::create($title, $body))
                    ->withData($payload);
                $result = $this->messaging->sendMulticast($message, $chunk);
                Log::info('[NotificationService] FCM result: success=' . $result->successes()->count() . ' failures=' . $result->failures()->count());
            } catch (\Throwable $e) {
                Log::error('[NotificationService] FCM Error: ' . $e->getMessage());
            }
        }
    }
}
