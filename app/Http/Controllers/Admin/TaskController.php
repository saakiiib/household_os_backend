<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Yajra\DataTables\Facades\DataTables;

class TaskController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(Task::with('household', 'createdBy', 'assignedUser')
                ->select('id', 'title', 'status', 'priority', 'due_date', 'household_id', 'assigned_user_id'))
                ->addColumn('title_link', function ($t) {
                    return '<a href="' . route('admin.tasks.show', $t) . '" class="fw-semibold text-body">' . e($t->title) . '</a>';
                })
                ->addColumn('household_link', function ($t) {
                    if (!$t->household) return 'N/A';
                    return '<a href="' . route('admin.households.show', $t->household) . '" class="text-body">' . e($t->household->name) . '</a>';
                })
                ->addColumn('assigned_name', fn($t) => $t->assignedUser
                    ? '<a href="' . route('admin.users.show', $t->assignedUser) . '" class="text-body">' . e($t->assignedUser->name) . '</a>'
                    : '<span class="text-muted">Unassigned</span>')
                ->addColumn('priority_badge', function ($t) {
                    $cls = match($t->priority) { 'high' => 'danger', 'medium' => 'warning', default => 'secondary' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($t->priority ?? 'Normal') . '</span>';
                })
                ->addColumn('status_badge', function ($t) {
                    $cls = match($t->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst(str_replace('_', ' ', $t->status)) . '</span>';
                })
                ->addColumn('due_date_fmt', fn($t) => $t->due_date ? $t->due_date->format('d M Y') : '-')
                ->rawColumns(['title_link', 'household_link', 'assigned_name', 'priority_badge', 'status_badge'])
                ->make(true);
        }

        return view('admin.pages.tasks');
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
