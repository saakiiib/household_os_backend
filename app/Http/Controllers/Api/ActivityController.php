<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    /**
     * GET /api/households/{household_id}/activities
     * List activities for a subject (LIFO — newest first).
     */
    public function index(Request $request, $household_id)
    {
        $query = ActivityLog::with('user:id,first_name,last_name,avatar')
            ->where('household_id', $household_id);

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // scope=mine → only activities performed by the current user, so the
        // dashboard can show a personal feed instead of the whole household's.
        if ($request->input('scope') === 'mine') {
            $query->where('user_id', Auth::id());
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 50))
            ->get()
            ->map(function ($log) {
                return [
                    'id'          => $log->id,
                    'action'      => $log->action,
                    'description' => $log->description,
                    'subject_type' => $log->subject_type,
                    'subject_id'  => $log->subject_id,
                    'user'        => $log->user ? [
                        'id'         => $log->user->id,
                        'name'       => $log->user->name,
                        'first_name' => $log->user->first_name,
                        'last_name'  => $log->user->last_name,
                        'avatar'     => $log->user->avatar,
                    ] : null,
                    'created_at'  => $log->created_at instanceof \DateTimeInterface
                        ? $log->created_at->toIso8601String()
                        : $log->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $activities,
        ]);
    }

    /**
     * Helper: log an activity.
     * Call this from other controllers.
     */
    public static function log(
        int $householdId,
        int $userId,
        string $subjectType,
        int $subjectId,
        string $action,
        ?string $description = null
    ): ActivityLog {
        return ActivityLog::create([
            'household_id'  => $householdId,
            'user_id'       => $userId,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'action'        => $action,
            'description'   => $description,
        ]);
    }
}
