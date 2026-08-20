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

        $payload = array_merge($data, ['notification_type' => $type, 'priority' => $priority]);
        $payload = array_map(fn($v) => is_string($v) ? $v : (string) $v, $payload);

        // iOS (APNS) is the primary target: vary the sound per priority.
        // 'default' uses the system sound; drop in named .aiff files in the
        // iOS app bundle (e.g. critical.aiff) and map them here for distinct tones.
        $iosSound = match ($priority) {
            'critical' => 'default',
            'high' => 'default',
            'low' => 'default',
            default => 'default',
        };

        $apns = ApnsConfig::fromArray([
            'payload' => [
                'aps' => [
                    'sound' => $iosSound,
                    'badge' => 1,
                ],
            ],
        ]);

        // Android: keep it working by using the channel the app already
        // creates ('household_os'). Per-priority Android channels are not used.
        $androidChannel = 'household_os';

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $messageBuilder = CloudMessage::new()
                    ->withNotification(FcmNotification::create($title, $body))
                    ->withData($payload)
                    ->withApnsConfig($apns)
                    ->withAndroidConfig([
                        'priority' => in_array($priority, ['critical', 'high']) ? 'high' : 'normal',
                        'ttl' => '86400s',
                        'notification' => [
                            'channel_id' => $androidChannel,
                        ],
                    ]);

                $result = $this->messaging->sendMulticast($messageBuilder, $chunk);

                Log::info("NOTIFICATION: FCM sent", [
                    'success' => $result->successes()->count(),
                    'failed'  => $result->failures()->count(),
                ]);

                foreach ($result->failures()->getItems() as $failure) {
                    $token = $failure->target()?->value();
                    $errorMsg = $failure->error()?->getMessage();

                    Log::info("NOTIFICATION: FCM delivery failure", [
                        'token' => $token,
                        'error' => $errorMsg,
                    ]);

                    if ($token) {
                        User::where('fcm_token', $token)->update(['fcm_token' => null]);
                        Log::info("NOTIFICATION: Cleared invalid FCM token for user — device must re-register.");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("NOTIFICATION: FCM Error: " . $e->getMessage());
            }
        }
    }
}
