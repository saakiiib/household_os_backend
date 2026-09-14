<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    /**
     * GET /api/households/{household_id}/activities
     * Permission-aware household activity feed.
     *
     * IMPORTANT: derived information inherits the source permission. A private
     * document activity is completely omitted for members who cannot currently
     * view that document. If access is later removed, the old activity also
     * disappears for that user because permission is checked at read time.
     */
    public function index(Request $request, $household_id)
    {
        $userId = Auth::id();
        $householdId = (int) $household_id;

        $isActiveMember = HouseholdMember::where('household_id', $householdId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this household.',
            ], 403);
        }

        $requestedLimit = max(1, min((int) $request->input('limit', 50), 100));

        $query = ActivityLog::with('user:id,first_name,last_name,avatar')
            ->where('household_id', $householdId);

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        if ($request->input('scope') === 'mine') {
            $query->where('user_id', $userId);
        }

        // Fetch extra rows because private-document filtering may remove some.
        $logs = $query->orderBy('created_at', 'desc')
            ->limit(min($requestedLimit * 4, 300))
            ->get();

        $documentIds = $logs->where('subject_type', 'document')->pluck('subject_id')->unique()->values();
        $renewalIds = $logs->where('subject_type', 'renewal')->pluck('subject_id')->unique()->values();
        $taskIds = $logs->where('subject_type', 'task')->pluck('subject_id')->unique()->values();

        $documents = Document::with('allowedMembers:id')
            ->where('household_id', $householdId)
            ->whereIn('id', $documentIds)
            ->get()->keyBy('id');
        $renewals = Renewal::where('household_id', $householdId)
            ->whereIn('id', $renewalIds)
            ->get(['id', 'title'])->keyBy('id');
        $tasks = Task::where('household_id', $householdId)
            ->whereIn('id', $taskIds)
            ->get(['id', 'title'])->keyBy('id');

        $activities = $logs->map(function ($log) use ($userId, $documents, $renewals, $tasks) {
            $subjectTitle = null;

            if ($log->subject_type === 'document') {
                $document = $documents->get($log->subject_id);
                if (!$document || !$document->canUserView($userId)) {
                    return null;
                }
                $subjectTitle = $document->title;
            } elseif ($log->subject_type === 'renewal') {
                $renewal = $renewals->get($log->subject_id);
                if (!$renewal) {
                    return null;
                }
                $subjectTitle = $renewal->title;
            } elseif ($log->subject_type === 'task') {
                $task = $tasks->get($log->subject_id);
                if (!$task) {
                    return null;
                }
                $subjectTitle = $task->title;
            }

            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'subject_title' => $subjectTitle,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'first_name' => $log->user->first_name,
                    'last_name' => $log->user->last_name,
                    'avatar' => $log->user->avatar,
                ] : null,
                'created_at' => $log->created_at instanceof \DateTimeInterface
                    ? $log->created_at->toIso8601String()
                    : $log->created_at,
            ];
        })->filter()->take($requestedLimit)->values();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    public static function log(
        int $householdId,
        int $userId,
        string $subjectType,
        int $subjectId,
        string $action,
        ?string $description = null
    ): ActivityLog {
        return ActivityLog::create([
            'household_id' => $householdId,
            'user_id' => $userId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
