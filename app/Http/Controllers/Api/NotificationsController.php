<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationsController extends Controller
{
    /**
     * GET /api/notifications
     * List user notifications with optional unread filter and pagination.
     */
    public function index(Request $request)
    {
        $query = Notification::where('user_id', Auth::id());

        if ($request->has('household_id')) {
            $query->where('household_id', $request->household_id);
        }

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $limit = $request->integer('limit', 20);
        $notifications = $query->orderBy('created_at', 'desc')->paginate($limit);

        $unreadCount = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data'    => $notifications->items(),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
            ],
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * PUT /api/notifications/{notification_id}/read
     * Mark a specific notification as read.
     */
    public function read($notification_id)
    {
        $notification = Notification::where('id', $notification_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $notification->update([
            'read_at' => now(),
            'status'  => 'read',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'      => $notification->id,
                'read_at' => $notification->read_at,
            ],
        ]);
    }

    /**
     * POST /api/notifications/read-all
     * Mark all notifications for user as read.
     */
    public function readAll(Request $request)
    {
        $query = Notification::where('user_id', Auth::id())->whereNull('read_at');

        if ($request->has('household_id')) {
            $query->where('household_id', $request->household_id);
        }

        $query->update([
            'read_at' => now(),
            'status'  => 'read',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * POST /api/notifications/fcm-token
     * Register or update user's FCM device token.
     */
    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $user->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token registered successfully',
        ]);
    }
}
