<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(User::with('households')
                ->select('id', 'first_name', 'last_name', 'email', 'status', 'is_admin', 'created_at'))
                ->addColumn('name', fn($user) => $user->name)
                ->addColumn('households_count', fn($user) => $user->households->count())
                ->addColumn('status_badge', fn($user) => $user->status === 'active'
                    ? '<span class="badge badge-soft-success">Active</span>'
                    : '<span class="badge badge-soft-danger">Inactive</span>')
                ->addColumn('date_fmt', fn($user) => $user->created_at->format('d M Y'))
                ->addColumn('action', function ($user) {
                    $toggleClass = $user->status === 'active' ? 'warning' : 'success';
                    $toggleText = $user->status === 'active' ? 'Deactivate' : 'Activate';
                    return '<div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown"><i class="ri-more-2-fill"></i></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="' . route('admin.users.show', $user) . '"><i class="ri-eye-line me-2"></i>View</a>
                            <button class="dropdown-item text-' . $toggleClass . '" onclick="toggleStatus(' . $user->id . ')"><i class="ri-toggle-' . ($user->status === 'active' ? 'on' : 'off') . '-line me-2"></i>' . $toggleText . '</button>
                        </div>
                    </div>';
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('admin.pages.users');
    }

    public function show(User $user)
    {
        $user->load('households', 'householdMemberships', 'payments', 'subscriptions');
        return view('admin.pages.user-show', compact('user'));
    }

    public function toggleStatus(User $user)
    {
        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        return response()->json(['success' => true, 'status' => $user->status]);
    }
}
