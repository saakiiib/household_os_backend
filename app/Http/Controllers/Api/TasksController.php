<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Task;
use App\Services\EntitlementService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TasksController extends Controller
{
    /**
     * GET /api/households/{household_id}/tasks
     * List tasks for the household.
     */
    public function index(Request $request, $household_id)
    {
        $query = Task::with(['assignedUser:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name'])
            ->where('household_id', $household_id);

        // Text search — title, description, assigned member name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('assignedUser', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('task_type')) {
            $query->where('task_type', $request->task_type);
        }
        if ($request->boolean('assigned_to_me')) {
            $query->where('assigned_user_id', Auth::id());
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('due_date_from')) {
            $query->where('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->where('due_date', '<=', $request->due_date_to);
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
     * Create a task with a single assignee.
     */
    public function store(Request $request, $household_id)
    {
        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'task_type'         => 'required|in:one-time,recurring',
            'priority'          => 'sometimes|in:low,medium,high',
            'frequency'         => 'nullable|required_if:task_type,recurring|in:daily,weekly,monthly',
            'due_date'          => 'required|date',
            'due_time'          => 'nullable|date_format:H:i',
            'reminder_before'   => 'nullable|in:15_minutes,1_hour,1_day,3_days,1_week',
            'repeat'            => 'nullable|in:does_not_repeat,daily,weekly,monthly',
            'notes'             => 'nullable|string|max:2000',
            'assigned_user_id'  => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Entitlement gate: free plan is limited to a number of active tasks.
        $household = Household::findOrFail($household_id);
        if (!(new EntitlementService())->canCreateTask($household)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your Free plan Task limit (' . EntitlementService::FREE_TASKS . ' active). Upgrade to unlock unlimited Tasks.',
                'code' => 'ENTITLEMENT_LIMIT_TASKS',
            ], 403);
        }

        $task = Task::create([
            'household_id'      => $household_id,
            'created_by_user_id' => Auth::id(),
            'assigned_user_id'  => $request->assigned_user_id,
            'title'             => $request->title,
            'description'       => $request->description,
            'task_type'         => $request->task_type,
            'priority'          => $request->priority ?? 'medium',
            'frequency'         => $request->frequency,
            'due_date'          => $request->due_date,
            'due_time'          => $request->due_time,
            'reminder_before'   => $request->reminder_before,
            'repeat'            => $request->repeat ?? 'does_not_repeat',
            'notes'             => $request->notes,
            'status'            => 'pending',
        ]);

        $task->load(['assignedUser:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        ActivityController::log($household_id, Auth::id(), 'task', $task->id, 'created', 'Task created');

        // Always send assignment notification
        \Log::info("TASK STORE: Sending notification to user {$task->assigned_user_id} for task {$task->id}");
        try {
            app(NotificationService::class)->sendToUser(
                $task->assigned_user_id,
                'New task assigned',
                'You have been assigned: ' . $task->title,
                'task_assigned',
                [
                    'module' => 'task',
                    'action_type' => 'task',
                    'action_id' => $task->id,
                    'type' => 'task',
                    'id' => $task->id,
                ],
                'high'
            );
        } catch (\Throwable $e) {
            \Log::error("TASK STORE: Notification failed: " . $e->getMessage());
        }

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
        $task = Task::with([
                'assignedUser:id,first_name,last_name,email,avatar',
                'createdBy:id,first_name,last_name',
                'parent:id,title,due_date,status,completed_at',
                'children:id,title,due_date,status,completed_at',
            ])
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
            'title'             => 'sometimes|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'task_type'         => 'sometimes|in:one-time,recurring',
            'priority'          => 'sometimes|in:low,medium,high',
            'status'            => 'sometimes|in:pending,in_progress,completed',
            'frequency'         => 'nullable|in:daily,weekly,monthly',
            'due_date'          => 'sometimes|date',
            'due_time'          => 'nullable|date_format:H:i',
            'reminder_before'   => 'nullable|in:15_minutes,1_hour,1_day,3_days,1_week',
            'repeat'            => 'nullable|in:does_not_repeat,daily,weekly,monthly',
            'notes'             => 'nullable|string|max:2000',
            'assigned_user_id'  => 'sometimes|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldAssignedUserId = $task->assigned_user_id;

        $task->update($request->only([
            'title', 'description', 'task_type', 'priority', 'status',
            'frequency', 'due_date', 'due_time', 'reminder_before', 'repeat', 'notes', 'assigned_user_id',
        ]));

        // Handle completion
        if ($request->status === 'completed' && !$task->completed_at) {
            $task->update(['completed_at' => now()]);
        }

        $task->load(['assignedUser:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        // Send notification if assignee changed
        \Log::info("TASK UPDATE: Checking notification for task {$task->id}, old_assigned={$oldAssignedUserId}, new_assigned=" . ($request->assigned_user_id ?? 'null'));

        if ($request->has('assigned_user_id') && $request->assigned_user_id != $oldAssignedUserId) {
            \Log::info("TASK UPDATE: Sending notification to user {$request->assigned_user_id}");
            try {
                app(NotificationService::class)->sendToUser(
                    $request->assigned_user_id,
                    'Task reassigned',
                    'You have been assigned: ' . $task->title,
                    'task_assigned',
                    [
                        'module' => 'task',
                        'action_type' => 'task',
                        'action_id' => $task->id,
                        'type' => 'task',
                        'id' => $task->id,
                    ],
                    'high'
                );
            } catch (\Throwable $e) {
                \Log::error("TASK UPDATE: Notification failed: " . $e->getMessage());
            }
        } else {
            \Log::info("TASK UPDATE: No assignee change, skipping notification");
        }

        ActivityController::log($household_id, Auth::id(), 'task', $task->id, 'updated', 'Task updated');

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => $this->formatTask($task),
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/tasks/{task_id}
     * Delete a task (creator or admin only).
     */
    public function destroy($household_id, $task_id)
    {
        $task = Task::where('household_id', $household_id)->findOrFail($task_id);

        // Only creator or admin can delete
        $isCreator = $task->created_by_user_id === Auth::id();
        if (!$isCreator) {
            $membership = HouseholdMember::where('household_id', $household_id)
                ->where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();
            if (!$membership || !$membership->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the task creator or an admin can delete tasks.',
                ], 403);
            }
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/tasks/{task_id}/start
     * Move a task from pending to in_progress.
     */
    public function startInProgress(Request $request, $household_id, $task_id)
    {
        $task = Task::where('household_id', $household_id)->findOrFail($task_id);

        // Only assigned user can start the task
        if ($task->assigned_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        if ($task->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending tasks can be started.',
            ], 409);
        }

        $task->update(['status' => 'in_progress']);

        $task->load(['assignedUser:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        ActivityController::log($household_id, Auth::id(), 'task', $task->id, 'started', 'Task started');

        return response()->json([
            'success' => true,
            'message' => 'Task started',
            'data' => $this->formatTask($task),
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/tasks/{task_id}/complete
     * Mark a task as completed.
     */
    public function complete(Request $request, $household_id, $task_id)
    {
        $task = Task::where('household_id', $household_id)->findOrFail($task_id);

        // Only assigned user can mark as complete
        if ($task->assigned_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        if ($task->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Task is already completed.',
            ], 409);
        }

        $task->update(['status' => 'completed', 'completed_at' => now()]);

        ActivityController::log($household_id, Auth::id(), 'task', $task->id, 'completed', 'Task completed');

        // Auto-create next instance for recurring tasks (using task_type OR repeat field)
        $newTask = null;
        $repeatFrequency = null;
        if ($task->repeat && $task->repeat !== 'does_not_repeat' && $task->due_date) {
            $repeatFrequency = $task->repeat;
        } elseif ($task->task_type === 'recurring' && $task->frequency && $task->due_date) {
            $repeatFrequency = $task->frequency;
        }

        if ($repeatFrequency) {
            $nextDueDate = $this->calculateNextDueDate($task->due_date, $repeatFrequency);

            $newTask = Task::create([
                'household_id'      => $household_id,
                'created_by_user_id' => $task->created_by_user_id,
                'assigned_user_id'  => $task->assigned_user_id,
                'parent_task_id'    => $task->id,
                'title'             => $task->title,
                'description'       => $task->description,
                'task_type'         => $task->task_type,
                'priority'          => $task->priority,
                'frequency'         => $task->frequency,
                'due_date'          => $nextDueDate,
                'due_time'          => $task->due_time,
                'reminder_before'   => $task->reminder_before,
                'repeat'            => $task->repeat,
                'notes'             => $task->notes,
                'status'            => 'pending',
            ]);

            $newTask->load(['assignedUser:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

            ActivityController::log($household_id, Auth::id(), 'task', $newTask->id, 'repeated', 'Task repeated');
        }

        $task->load(['assignedUser:id,first_name,last_name,email,avatar', 'createdBy:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'Task marked as complete',
            'data' => $this->formatTask($task),
            'next_task' => $newTask ? $this->formatTask($newTask) : null,
        ]);
    }

    private function calculateNextDueDate(string $currentDueDate, string $frequency): string
    {
        $date = new \DateTime($currentDueDate);

        switch ($frequency) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+7 days');
                break;
            case 'biweekly':
                $date->modify('+14 days');
                break;
            case 'monthly':
                $day = (int) $date->format('d');
                $date->modify('+1 month');
                // If the original day exceeded the new month's last day, use the last day
                $maxDay = (int) $date->format('t');
                if ($day > $maxDay) {
                    $date->setDate((int) $date->format('Y'), (int) $date->format('m'), $maxDay);
                } else {
                    $date->setDate((int) $date->format('Y'), (int) $date->format('m'), $day);
                }
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
        }

        return $date->format('Y-m-d');
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
            'due_date'         => $task->due_date instanceof \DateTimeInterface ? $task->due_date->format('Y-m-d') : $task->due_date,
            'due_date_formatted' => $task->due_date instanceof \DateTimeInterface ? $task->due_date->format('d-m-y') : $task->due_date,
            'due_time'         => $task->due_time instanceof \DateTimeInterface ? $task->due_time->format('H:i') : $task->due_time,
            'reminder_before'  => $task->reminder_before,
            'repeat'           => $task->repeat ?? 'does_not_repeat',
            'completed_at'     => $task->completed_at instanceof \DateTimeInterface ? $task->completed_at->toIso8601String() : $task->completed_at,
            'notes'            => $task->notes,
            'is_overdue'       => $task->is_overdue,
            'is_repeating'     => $task->is_repeating,
            'days_until_due'   => $task->days_until_due,
            'parent_task_id'   => $task->parent_task_id,
            'assigned_user'    => $task->assignedUser ? [
                'id'         => $task->assignedUser->id,
                'first_name' => $task->assignedUser->first_name,
                'last_name'  => $task->assignedUser->last_name,
                'email'      => $task->assignedUser->email,
                'avatar'     => $task->assignedUser->avatar,
                'name'       => $task->assignedUser->name,
            ] : null,
            'created_by'       => $task->createdBy ? [
                'id'   => $task->createdBy->id,
                'name' => $task->createdBy->name,
            ] : null,
            'parent'           => $task->parent ? [
                'id'           => $task->parent->id,
                'title'        => $task->parent->title,
                'due_date'     => $task->parent->due_date instanceof \DateTimeInterface ? $task->parent->due_date->format('Y-m-d') : $task->parent->due_date,
                'status'       => $task->parent->status,
                'completed_at' => $task->parent->completed_at instanceof \DateTimeInterface ? $task->parent->completed_at->toIso8601String() : $task->parent->completed_at,
            ] : null,
            'children'         => $task->children->map(fn($child) => [
                'id'           => $child->id,
                'title'        => $child->title,
                'due_date'     => $child->due_date instanceof \DateTimeInterface ? $child->due_date->format('Y-m-d') : $child->due_date,
                'status'       => $child->status,
                'completed_at' => $child->completed_at instanceof \DateTimeInterface ? $child->completed_at->toIso8601String() : $child->completed_at,
            ]),
            'created_at'       => $task->created_at instanceof \DateTimeInterface ? $task->created_at->toIso8601String() : $task->created_at,
            'updated_at'       => $task->updated_at instanceof \DateTimeInterface ? $task->updated_at->toIso8601String() : $task->updated_at,
        ];
    }
}
