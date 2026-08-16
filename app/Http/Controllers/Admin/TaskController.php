<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;

class TaskController extends Controller
{
    public function index()
    {
        $totalTasks = \App\Models\Task::count();
        $completedTasks = \App\Models\Task::where('status', 'completed')->count();
        $inProgressTasks = \App\Models\Task::where('status', 'in_progress')->count();
        $pendingTasks = \App\Models\Task::where('status', 'pending')->count();

        return view('admin.pages.tasks', compact(
            'totalTasks', 'completedTasks', 'inProgressTasks', 'pendingTasks'
        ));
    }

    public function show(Task $task)
    {
        $task->load('household', 'createdBy', 'assignedUser', 'parent', 'children.assignedUser');

        $siblings = Task::where('household_id', $task->household_id)
            ->where('id', '!=', $task->id)
            ->with('assignedUser')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.pages.task-show', compact('task', 'siblings'));
    }
}
