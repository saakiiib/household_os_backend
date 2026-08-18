<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(protected Messaging $messaging) {}

    /**
     * Send notification to a single user (in-app + FCM push).
     *
     * Priority levels: 'critical' | 'high' | 'normal' | 'low'
     *   critical — overdue, subscription expiry, security alerts
     *   high     — due today, day-before reminders
     *   normal   — upcoming reminders, daily digests
     *   low      — weekly summaries, tips
     */
    public function sendToUser(int $userId, string $title, string $body, string $type, array $data = [], string $priority = 'normal'): void
    {
        $user = User::find($userId);
        if (!$user) {
            Log::warning("NOTIFICATION: User {$userId} not found, skipping.");
            return;
        }

        Log::info("NOTIFICATION: Sending to user {$userId} ({$user->email})", [
            'title'    => $title,
            'body'     => $body,
            'type'     => $type,
            'priority' => $priority,
            'data'     => $data,
        ]);

        $this->saveToDb($userId, $title, $body, $type, $data, $priority);
        $this->sendFcm([$user], $title, $body, $type, $data, $priority);

        Log::info("NOTIFICATION: Done for user {$userId}");
    }

    /**
     * Send notification to multiple users (in-app + FCM push).
     */
    public function sendToUsers(array $userIds, string $title, string $body, string $type, array $data = [], string $priority = 'normal'): void
    {
        $users = User::whereIn('id', $userIds)->get();

        Log::info("NOTIFICATION: Sending to " . $users->count() . " users", [
            'user_ids'  => $userIds,
            'title'     => $title,
            'type'      => $type,
            'priority'  => $priority,
        ]);

        foreach ($users as $user) {
            $this->saveToDb($user->id, $title, $body, $type, $data, $priority);
        }
        $this->sendFcm($users->all(), $title, $body, $type, $data, $priority);

        Log::info("NOTIFICATION: Done for " . $users->count() . " users");
    }

    /**
     * Save notification to database only (for in-app display).
     */
    private function saveToDb(int $userId, string $title, string $body, string $type, array $data, string $priority = 'normal'): void
    {
        Notification::create([
            'user_id'  => $userId,
            'title'    => $title,
            'body'     => $body,
            'type'     => $type,
            'priority' => $priority,
            'data'     => $data,
        ]);

        Log::info("NOTIFICATION: Saved to DB for user {$userId}");
    }

    /**
     * Send FCM push notification to devices.
     */
    private function sendFcm(array $users, string $title, string $body, string $type, array $data, string $priority = 'normal'): void
    {
        $tokens = collect($users)
            ->filter(fn($u) => !empty($u->fcm_token))
            ->pluck('fcm_token')
            ->values()
            ->all();

        if (empty($tokens)) {
            Log::warning("NOTIFICATION: No FCM tokens found for users. Push not sent.", [
                'user_ids' => collect($users)->pluck('id')->toArray(),
            ]);
            return;
        }

        Log::info("NOTIFICATION: Sending FCM push to " . count($tokens) . " devices", [
            'tokens_count' => count($tokens),
        ]);

        $payload = array_merge($data, ['type' => $type]);
        $payload = array_map(fn($v) => is_string($v) ? $v : (string) $v, $payload);

        $apns = ApnsConfig::fromArray([
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                    'badge' => 1,
                ],
            ],
        ]);

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $messageBuilder = CloudMessage::new()
                    ->withNotification(FcmNotification::create($title, $body))
                    ->withData($payload)
                    ->withApnsConfig($apns);

                if (in_array($priority, ['critical', 'high'])) {
                    $messageBuilder = $messageBuilder->withAndroidConfig([
                        'priority' => 'high',
                        'ttl' => '86400s',
                    ]);
                }

                $result = $this->messaging->sendMulticast($messageBuilder, $chunk);

                Log::info("NOTIFICATION: FCM sent", [
                    'success' => $result->successes()->count(),
                    'failed'  => $result->failures()->count(),
                ]);

                foreach ($result->failures() as $failure) {
                    Log::error("NOTIFICATION: FCM delivery failure", [
                        'token'   => $failure->target()?->value(),
                        'error'   => $failure->error()?->getMessage(),
                        'response' => $failure->response() instanceof \Psr\Http\Message\ResponseInterface
                            ? json_decode((string) $failure->response()->getBody(), true)
                            : $failure->response(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("NOTIFICATION: FCM Error: " . $e->getMessage());
            }
        }
    }
}
