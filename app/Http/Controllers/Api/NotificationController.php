<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * List notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id);

        // When `unread=1` is requested (the notification bell/inbox), return only
        // unread items so the list acts as an "unread-only" view and read items
        // are naturally unlisted. Without the flag, all notifications are returned.
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $notifications->items(),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     * Get unread notification count.
     */
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, $id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['read_at' => Carbon::now()]);

        return response()->json([
            'success' => true,
            'message' => 'Marked as read.',
        ]);
    }

    /**
     * POST /api/notifications/read-all
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);

        return response()->json([
            'success' => true,
            'message' => 'All marked as read.',
        ]);
    }

    /**
     * POST /api/fcm-token
     * Save or update FCM token for the authenticated user.
     */
    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'platform'  => 'nullable|string|in:ios,android,web',
        ]);

        $user = $request->user();
        $token = $request->fcm_token;

        // If the token was previously registered to a different user (login
        // switch, account change on the same device) take ownership and remove
        // the stale binding so the old user no longer receives push on this device.
        DeviceToken::where('token', $token)
            ->where('user_id', '!=', $user->id)
            ->delete();

        // Keep the legacy single-token column updated for backwards compatibility.
        $user->update(['fcm_token' => $token]);

        // Upsert into the per-device tokens table so multiple devices can each
        // receive push notifications for the same user.
        DeviceToken::updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $request->input('platform')]
        );

        return response()->json([
            'success' => true,
            'message' => 'Token saved.',
        ]);
    }

    /**
     * DELETE /api/fcm-token
     * Remove FCM token (on logout).
     */
    public function deleteFcmToken(Request $request)
    {
        $user = $request->user();

        $token = $user->fcm_token;
        $user->update(['fcm_token' => null]);

        // Drop every device-token row bound to this user. Using only the
        // stored fcm_token would miss multi-device entries that the
        // per-device `device_tokens` table tracks separately.
        if (!empty($token)) {
            DeviceToken::where('token', $token)->delete();
        }
        DeviceToken::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token removed.',
        ]);
    }
}
