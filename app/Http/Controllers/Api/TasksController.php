<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Events\TaskUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TasksController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Format a task for API responses.
     */
    private function formatTask(Task $task): array
    {
        return [
            'id'                  => $task->id,
            'household_id'        => $task->household_id,
            'title'               => $task->title,
            'description'         => $task->description,
            'task_type'           => $task->task_type,
            'status'              => $task->status,
            'frequency'           => $task->frequency,
            'due_date'            => $task->due_date?->toDateString(),
            'completed_at'        => $task->completed_at,
            'priority'            => $task->priority,
            'reward_points'       => $task->reward_points,
            'estimated_hours'     => $task->estimated_hours,
            'icon'                => $task->icon,
            'color'               => $task->color,
            'notes'               => $task->notes,
            'created_by'          => $task->createdBy ? ['id' => $task->createdBy->id, 'name' => $task->createdBy->name] : null,
            'assigned_to'         => $task->assignedTo ? ['id' => $task->assignedTo->id, 'name' => $task->assignedTo->name] : null,
            'completed_by'        => $task->completedBy ? ['id' => $task->completedBy->id, 'name' => $task->completedBy->name] : null,
            'created_at'          => $task->created_at,
            'updated_at'          => $task->updated_at,
        ];
    }

    /**
     * Calculate the next due date for a recurring task.
     */
    private function nextDueDate(Task $task): ?Carbon
    {
        if (!$task->due_date || !$task->frequency) {
            return null;
        }

        return match ($task->frequency) {
            'daily'   => $task->due_date->addDay(),
            'weekly'  => $task->due_date->addWeek(),
            'monthly' => $task->due_date->addMonth(),
            'yearly'  => $task->due_date->addYear(),
            default   => null,
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/households/{household_id}/tasks
     * List tasks with optional filters: status, priority, assigned_to, due_before.
     * Requires: active household member.
     */
    public function index(Request $request, $household_id)
    {
        $query = Task::with(['assignedTo', 'createdBy', 'completedBy'])
            ->where('household_id', $household_id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to_user_id', $request->assigned_to);
        }

        if ($request->has('task_type')) {
            $query->where('task_type', $request->task_type);
        }

        if ($request->has('due_before')) {
            $query->whereDate('due_date', '<=', $request->due_before);
        }

        $tasks = $query->orderBy('due_date')->orderBy('priority', 'desc')
                       ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => collect($tasks->items())->map(fn($t) => $this->formatTask($t)),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'per_page'     => $tasks->perPage(),
                'total'        => $tasks->total(),
                'last_page'    => $tasks->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/households/{household_id}/tasks
     * Create a new task.
     * Requires: active household member.
     */
    public function store(Request $request, $household_id)
    {
        $validator = Validator::make($request->all(), [
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'task_type'            => 'required|in:one-time,recurring,rotating',
            'assigned_to_user_id'  => 'nullable|integer|exists:users,id',
            'due_date'             => 'nullable|date',
            'frequency'            => 'nullable|in:daily,weekly,monthly,yearly',
            'priority'             => 'nullable|in:low,medium,high',
            'reward_points'        => 'nullable|integer|min:0',
            'estimated_hours'      => 'nullable|numeric|min:0',
            'icon'                 => 'nullable|string|max:100',
            'color'                => 'nullable|string|max:7',
            'notes'                => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Validate that assigned user is a household member
        if ($request->filled('assigned_to_user_id')) {
            $isMember = HouseholdMember::where('household_id', $household_id)
                ->where('user_id', $request->assigned_to_user_id)
                ->where('status', 'active')
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assigned user is not an active member of this household.',
                ], 422);
            }
        }

        $task = Task::create([
            'household_id'        => $household_id,
            'created_by_user_id'  => Auth::id(),
            'assigned_to_user_id' => $request->assigned_to_user_id,
            'title'               => $request->title,
            'description'         => $request->description,
            'task_type'           => $request->task_type,
            'status'              => 'pending',
            'frequency'           => $request->frequency,
            'due_date'            => $request->due_date,
            'priority'            => $request->priority ?? 'medium',
            'reward_points'       => $request->reward_points ?? 0,
            'estimated_hours'     => $request->estimated_hours,
            'icon'                => $request->icon,
            'color'               => $request->color,
            'notes'               => $request->notes,
        ]);

        $task->load(['assignedTo', 'createdBy']);

        event(new TaskUpdated($task, 'created'));

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data'    => $this->formatTask($task),
        ], 201);
    }

    /**
     * GET /api/tasks/{task_id}
     * Get a single task.
     * Requires: active household member.
     */
    public function show(Request $request, $task_id)
    {
        $task = Task::with(['assignedTo', 'createdBy', 'completedBy', 'completions.completedBy'])
            ->findOrFail($task_id);

        // Ensure requester is a member of the household
        $isMember = HouseholdMember::where('household_id', $task->household_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $formatted = $this->formatTask($task);
        $formatted['completions'] = $task->completions->map(fn($c) => [
            'id'           => $c->id,
            'completed_by' => $c->completedBy ? ['id' => $c->completedBy->id, 'name' => $c->completedBy->name] : null,
            'completed_at' => $c->completed_at,
            'notes'        => $c->notes,
            'photo_proof'  => $c->photo_proof,
        ]);

        return response()->json(['success' => true, 'data' => $formatted]);
    }

    /**
     * PATCH /api/tasks/{task_id}
     * Update a task. Admin/co-admin can update any task; members only their own.
     * Requires: authenticated + household membership.
     */
    public function update(Request $request, $task_id)
    {
        $task = Task::findOrFail($task_id);

        $membership = HouseholdMember::where('household_id', $task->household_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Members can only edit tasks assigned to or created by them
        if (!$membership->isAdminOrCoAdmin()) {
            if ($task->created_by_user_id !== Auth::id() && $task->assigned_to_user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit tasks assigned to or created by you.',
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'title'               => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'task_type'           => 'sometimes|in:one-time,recurring,rotating',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'due_date'            => 'nullable|date',
            'frequency'           => 'nullable|in:daily,weekly,monthly,yearly',
            'priority'            => 'nullable|in:low,medium,high',
            'status'              => 'sometimes|in:pending,in_progress,completed,on_hold',
            'reward_points'       => 'nullable|integer|min:0',
            'estimated_hours'     => 'nullable|numeric|min:0',
            'icon'                => 'nullable|string|max:100',
            'color'               => 'nullable|string|max:7',
            'notes'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $task->update($request->only([
            'title', 'description', 'task_type', 'assigned_to_user_id',
            'due_date', 'frequency', 'priority', 'status',
            'reward_points', 'estimated_hours', 'icon', 'color', 'notes',
        ]));

        $task->load(['assignedTo', 'createdBy', 'completedBy']);

        event(new TaskUpdated($task, 'updated'));

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data'    => $this->formatTask($task),
        ]);
    }

    /**
     * DELETE /api/tasks/{task_id}
     * Delete a task. Admin/co-admin or task creator.
     */
    public function destroy(Request $request, $task_id)
    {
        $task = Task::findOrFail($task_id);

        $membership = HouseholdMember::where('household_id', $task->household_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if (!$membership->isAdminOrCoAdmin() && $task->created_by_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins, co-admins, or the task creator can delete tasks.',
            ], 403);
        }

        event(new TaskUpdated($task, 'deleted'));

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * POST /api/tasks/{task_id}/complete
     * Mark a task as completed. If recurring, spawns the next occurrence.
     * Requires: authenticated + household membership.
     */
    public function complete(Request $request, $task_id)
    {
        $task = Task::findOrFail($task_id);

        $isMember = HouseholdMember::where('household_id', $task->household_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($task->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Task is already completed.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'notes'       => 'nullable|string',
            'photo_proof' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $now = now();

        // Log the completion
        TaskCompletion::create([
            'task_id'             => $task->id,
            'completed_by_user_id' => Auth::id(),
            'completed_at'        => $now,
            'notes'               => $request->notes,
            'photo_proof'         => $request->photo_proof,
        ]);

        // Mark original task completed
        $task->update([
            'status'              => 'completed',
            'completed_at'        => $now,
            'completed_by_user_id' => Auth::id(),
        ]);

        // For recurring/rotating tasks: spawn the next occurrence
        $nextTaskId = null;
        if ($task->isRecurring() && $task->frequency) {
            $nextDue = $this->nextDueDate($task);
            $nextTask = Task::create([
                'household_id'        => $task->household_id,
                'created_by_user_id'  => $task->created_by_user_id,
                'assigned_to_user_id' => $task->assigned_to_user_id,
                'title'               => $task->title,
                'description'         => $task->description,
                'task_type'           => $task->task_type,
                'status'              => 'pending',
                'frequency'           => $task->frequency,
                'due_date'            => $nextDue,
                'priority'            => $task->priority,
                'reward_points'       => $task->reward_points,
                'estimated_hours'     => $task->estimated_hours,
                'icon'                => $task->icon,
                'color'               => $task->color,
                'notes'               => $task->notes,
            ]);
            $nextTaskId = $nextTask->id;
        }

        event(new TaskUpdated($task, 'completed'));

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully',
            'data'    => [
                'id'           => $task->id,
                'status'       => 'completed',
                'completed_at' => $now,
                'next_task_id' => $nextTaskId,
            ],
        ]);
    }
}
