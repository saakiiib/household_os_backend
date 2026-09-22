<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index()
    {
        $isDataTable = request()->ajax() && request()->has('draw');

        if ($isDataTable) {
            $query = User::with('households.subscription.plan')
                ->where('is_admin', false)
                ->select('id', 'first_name', 'last_name', 'email', 'status', 'is_admin', 'created_at');

            return DataTables::of($query)
                ->addColumn('name', fn($user) => $user->name)
                ->addColumn('name_link', function ($user) {
                    return '<a href="' . route('admin.users.show', $user) . '" class="fw-semibold text-body">' . e($user->name) . '</a>';
                })
                ->addColumn('email_link', function ($user) {
                    return '<a href="' . route('admin.users.show', $user) . '" class="text-muted">' . e($user->email) . '</a>';
                })
                ->addColumn('households_count', fn($user) => $user->households->count())
                ->addColumn('household_fmt', function($user){$h=$user->households->first();return $h?'<a href="'.route('admin.households.show',$h).'">'.e($h->name).'</a>':'<span class="text-muted">None</span>';})
                ->addColumn('role_badge', function($user){$h=$user->households->first();$role=$h?($h->pivot->role??'member'):'—';return '<span class="badge bg-soft-secondary">'.e(ucfirst($role==='admin'?'Coordinator':$role)).'</span>';})
                ->addColumn('plan_fmt', function($user){$h=$user->households->first();if(!$h)return '—';return e(ucfirst((new \App\Services\EntitlementService())->getPlanCode($h)));})
                ->addColumn('subscription_fmt', function($user){$s=$user->households->first()?->subscription;return $s?'<span class="badge badge-soft-'.(in_array($s->status,['active','trial'])?'success':'warning').'">'.e(ucfirst(str_replace('_',' ',$s->status))).'</span>':'<span class="badge badge-soft-secondary">Free</span>';})
                ->addColumn('date_fmt', fn($user) => $user->created_at->format('d M Y'))
                ->addColumn('action', function ($user) {
                    return '<a href="' . route('admin.users.show', $user) . '" class="btn btn-sm btn-soft-primary">View</a>';
                })
                ->rawColumns(['name_link', 'email_link', 'household_fmt', 'role_badge', 'subscription_fmt', 'action'])
                ->make(true);
        }

        $totalUsers = User::where('is_admin', false)->count();
        $activeUsers = User::where('is_admin', false)->where('status', 'active')->count();
        $verifiedUsers = User::where('is_admin', false)->whereNotNull('email_verified_at')->count();
        $newUsers = User::where('is_admin', false)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.pages.users', compact(
            'totalUsers',
            'activeUsers',
            'verifiedUsers',
            'newUsers'
        ));
    }

    public function show(User $user)
    {
        $user->load(
            'households',
            'householdMemberships',
            'payments.household',
            'subscriptions.household',
            'subscriptions.plan'
        );

        $tasksAsCreator = \App\Models\Task::with('household', 'assignedUser')
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        $tasksAsAssignee = \App\Models\Task::with('household', 'createdBy')
            ->where('assigned_user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'households' => $user->households->count(),
            'tasks_created' => $tasksAsCreator->count(),
            'tasks_assigned' => $tasksAsAssignee->count(),
            'payments_count' => $user->payments->count(),
            'payments_total' => $user->payments->whereIn('status', ['succeeded', 'completed'])->sum('amount'),
        ];

        return view('admin.pages.user-show', compact('user', 'tasksAsCreator', 'tasksAsAssignee', 'stats'));
    }

    public function toggleStatus(User $user)
    {
        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        return response()->json(['success' => true, 'status' => $user->status]);
    }
}
