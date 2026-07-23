<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

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
        $request->validate(['fcm_token' => 'required|string']);

        $request->user()->update(['fcm_token' => $request->fcm_token]);

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
        $request->user()->update(['fcm_token' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Token removed.',
        ]);
    }
}
