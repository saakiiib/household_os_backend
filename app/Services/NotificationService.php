<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     * Channels can be a list: ['in_app', 'push', 'email', 'sms'].
     */
    public static function send(array $params): Notification
    {
        $householdId = $params['household_id'];
        $userId      = $params['user_id'];
        $type        = $params['notification_type'];
        $title       = $params['title'];
        $message     = $params['message'];
        $data        = $params['data'] ?? [];
        $actionUrl   = $params['action_url'] ?? null;
        $priority    = $params['priority'] ?? 'normal';
        $channels    = $params['channels'] ?? ['in_app'];

        // 1. Create In-App Notification entry in Database
        $notification = Notification::create([
            'household_id'      => $householdId,
            'user_id'           => $userId,
            'notification_type' => $type,
            'title'             => $title,
            'message'           => $message,
            'data'              => $data,
            'action_url'        => $actionUrl,
            'priority'          => $priority,
            'channels'          => $channels,
            'status'            => 'pending',
        ]);

        // 2. Dispatch to requested channels
        $sentStatus = 'sent';
        $failedReason = null;

        if (in_array('push', $channels)) {
            $user = User::find($userId);
            if ($user && $user->fcm_token) {
                // FCM Mock Push Delivery
                Log::channel('single')->info("FCM MOCK PUSH to user {$userId} [Token: {$user->fcm_token}]: {$title} - {$message}");
            } else {
                $sentStatus = 'failed';
                $failedReason = 'FCM token not registered for user.';
            }
        }

        // Update notification dispatch status
        $notification->update([
            'status'        => $sentStatus,
            'sent_at'       => $sentStatus === 'sent' ? now() : null,
            'failed_reason' => $failedReason,
        ]);

        return $notification;
    }
}
