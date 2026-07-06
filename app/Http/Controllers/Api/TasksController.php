<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TasksController extends Controller
{
    /**
     * GET /api/households/{household_id}/tasks
     * List tasks for the household. Supports filters: status, task_type, assigned_to_me.
     */
    public function index(Request $request, $household_id)
    {
        $query = Task::with(['assignedUsers:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name'])
            ->where('household_id', $household_id);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('task_type')) {
            $query->where('task_type', $request->task_type);
        }
        if ($request->boolean('assigned_to_me')) {
            $query->whereHas('assignedUsers', fn($q) => $q->where('users.id', Auth::id()));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->orderByRaw("FIELD(status, 'pending', 'in_progress', 'completed')")
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($task) {
                return $this->formatTask($task);
            });

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * POST /api/households/{household_id}/tasks
     * Create a task with assignees.
     */
    public function store(Request $request, $household_id)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'task_type'       => 'required|in:one-time,rotating,recurring',
            'priority'        => 'sometimes|in:low,medium,high',
            'frequency'       => 'nullable|required_if:task_type,recurring|in:daily,weekly,biweekly,monthly,yearly',
            'due_date'        => 'nullable|date',
            'notes'           => 'nullable|string|max:2000',
            'assigned_user_ids' => 'required|array|min:1',
            'assigned_user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $task = Task::create([
            'household_id'     => $household_id,
            'created_by_user_id' => Auth::id(),
            'title'            => $request->title,
            'description'      => $request->description,
            'task_type'        => $request->task_type,
            'priority'         => $request->priority ?? 'medium',
            'frequency'        => $request->frequency,
            'due_date'         => $request->due_date,
            'notes'            => $request->notes,
            'status'           => 'pending',
        ]);

        // Attach assignees
        foreach ($request->assigned_user_ids as $userId) {
            TaskAssignment::create([
                'task_id'  => $task->id,
                'user_id'  => $userId,
                'status'   => 'pending',
            ]);
        }

        $task->load(['assignedUsers:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => $this->formatTask($task),
        ], 201);
    }

    /**
     * GET /api/households/{household_id}/tasks/{task_id}
     * Show a single task.
     */
    public function show($household_id, $task_id)
    {
        $task = Task::with(['assignedUsers:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name'])
            ->where('household_id', $household_id)
            ->findOrFail($task_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatTask($task),
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/tasks/{task_id}
     * Update a task.
     */
    public function update(Request $request, $household_id, $task_id)
    {
        $task = Task::where('household_id', $household_id)->findOrFail($task_id);

        $validator = Validator::make($request->all(), [
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'task_type'       => 'sometimes|in:one-time,rotating,recurring',
            'priority'        => 'sometimes|in:low,medium,high',
            'status'          => 'sometimes|in:pending,in_progress,completed',
            'frequency'       => 'nullable|in:daily,weekly,biweekly,monthly,yearly',
            'due_date'        => 'nullable|date',
            'notes'           => 'nullable|string|max:2000',
            'assigned_user_ids' => 'sometimes|array|min:1',
            'assigned_user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $task->update($request->only([
            'title', 'description', 'task_type', 'priority', 'status',
            'frequency', 'due_date', 'notes',
        ]));

        // Handle completion
        if ($request->status === 'completed' && !$task->completed_at) {
            $task->update(['completed_at' => now()]);
            $task->assignments()->update(['status' => 'completed', 'completed_at' => now()]);
        }

        // Reassign if provided
        if ($request->has('assigned_user_ids')) {
            $task->assignments()->delete();
            foreach ($request->assigned_user_ids as $userId) {
                TaskAssignment::create([
                    'task_id'  => $task->id,
                    'user_id'  => $userId,
                    'status'   => $request->status === 'completed' ? 'completed' : 'pending',
                ]);
            }
        }

        $task->load(['assignedUsers:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => $this->formatTask($task),
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/tasks/{task_id}
     * Delete a task.
     */
    public function destroy($household_id, $task_id)
    {
        $task = Task::where('household_id', $household_id)->findOrFail($task_id);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/tasks/{task_id}/complete
     * Mark a specific assignment as completed.
     */
    public function complete(Request $request, $household_id, $task_id)
    {
        $task = Task::where('household_id', $household_id)->findOrFail($task_id);

        // Complete for the current user
        $assignment = $task->assignments()->where('user_id', Auth::id())->first();
        if ($assignment && $assignment->status !== 'completed') {
            $assignment->update(['status' => 'completed', 'completed_at' => now()]);
        }

        // Check if all assignees completed
        $allCompleted = $task->assignments()->where('status', '!=', 'completed')->count() === 0;
        if ($allCompleted) {
            $task->update(['status' => 'completed', 'completed_at' => now()]);
        } else {
            $task->update(['status' => 'in_progress']);
        }

        $task->load(['assignedUsers:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'Task completed',
            'data' => $this->formatTask($task),
        ]);
    }

    private function formatTask(Task $task): array
    {
        return [
            'id'               => $task->id,
            'household_id'     => $task->household_id,
            'title'            => $task->title,
            'description'      => $task->description,
            'task_type'        => $task->task_type,
            'priority'         => $task->priority,
            'status'           => $task->status,
            'frequency'        => $task->frequency,
            'due_date'         => $task->due_date?->format('Y-m-d'),
            'completed_at'     => $task->completed_at?->toIso8601String(),
            'notes'            => $task->notes,
            'is_overdue'       => $task->is_overdue,
            'days_until_due'   => $task->days_until_due,
            'assigned_users'   => $task->assignedUsers->map(fn($u) => [
                'id'         => $u->id,
                'first_name' => $u->first_name,
                'last_name'  => $u->last_name,
                'email'      => $u->email,
                'avatar'     => $u->avatar,
                'name'       => $u->name,
                'completed'  => $u->pivot->status === 'completed',
                'completed_at' => $u->pivot->completed_at?->toIso8601String(),
            ]),
            'created_by'       => $task->createdBy ? [
                'id'   => $task->createdBy->id,
                'name' => $task->createdBy->name,
            ] : null,
            'created_at'       => $task->created_at->toIso8601String(),
            'updated_at'       => $task->updated_at->toIso8601String(),
        ];
    }
}
