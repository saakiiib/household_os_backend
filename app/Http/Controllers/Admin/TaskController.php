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
                ->select('id', 'title', 'status', 'due_date', 'household_id', 'assigned_user_id'))
                ->addColumn('household_name', fn($t) => $t->household->name ?? 'N/A')
                ->addColumn('assigned_name', fn($t) => $t->assignedUser->name ?? 'Unassigned')
                ->addColumn('status_badge', function ($t) {
                    $cls = match($t->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst(str_replace('_', ' ', $t->status)) . '</span>';
                })
                ->addColumn('due_date_fmt', fn($t) => $t->due_date ? $t->due_date->format('d M Y') : '-')
                ->make(true);
        }

        return view('admin.pages.tasks');
    }
}
