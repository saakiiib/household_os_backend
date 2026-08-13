<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Household;
use Yajra\DataTables\Facades\DataTables;

class HouseholdController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(Household::with('creator', 'members', 'subscription')
                ->select('id', 'name', 'invite_code', 'created_by_user_id', 'created_at'))
                ->addColumn('name_link', function ($h) {
                    return '<a href="' . route('admin.households.show', $h) . '" class="fw-semibold text-body">' . e($h->name) . '</a>';
                })
                ->addColumn('creator_name', function ($h) {
                    if (!$h->creator) return 'N/A';
                    return '<a href="' . route('admin.users.show', $h->creator) . '" class="text-body">' . e($h->creator->name) . '</a>';
                })
                ->addColumn('members_count', function ($h) {
                    return '<a href="' . route('admin.households.show', $h) . '#members" class="text-body">' . $h->members->count() . ' · <span class="text-muted">View</span></a>';
                })
                ->addColumn('subscription_status', fn($h) => $h->subscription
                    ? '<span class="badge badge-soft-' . ($h->subscription->status === 'active' ? 'success' : 'warning') . '">' . ucfirst($h->subscription->status) . '</span>'
                    : '<span class="badge badge-soft-secondary">None</span>')
                ->addColumn('date_fmt', fn($h) => $h->created_at->format('d M Y'))
                ->addColumn('action', function ($h) {
                    return '<div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown"><i class="ri-more-2-fill"></i></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="' . route('admin.households.show', $h) . '"><i class="ri-eye-line me-2"></i>View</a>
                        </div>
                    </div>';
                })
                ->rawColumns(['name_link', 'creator_name', 'members_count', 'subscription_status', 'action'])
                ->make(true);
        }

        return view('admin.pages.households', [
            'households' => Household::withCount('members')->latest()->paginate(20),
        ]);
    }

    public function show(Household $household)
    {
        $household->load(
            'creator',
            'members',
            'subscription.plan',
            'payments.user',
            'invitations'
        );

        $tasks = \App\Models\Task::with('assignedUser', 'createdBy')
            ->where('household_id', $household->id)
            ->latest()
            ->get();

        $renewals = \App\Models\Renewal::with('createdBy')
            ->where('household_id', $household->id)
            ->latest()
            ->get();

        $documents = \App\Models\Document::with('createdBy', 'files')
            ->where('household_id', $household->id)
            ->latest()
            ->get();

        $stats = [
            'members' => $household->members->count(),
            'tasks_total' => $tasks->count(),
            'tasks_pending' => $tasks->where('status', '!=', 'completed')->count(),
            'renewals_total' => $renewals->count(),
            'renewals_overdue' => $renewals->filter(fn($r) => $r->is_overdue)->count(),
            'documents_total' => $documents->count(),
            'payments_total' => $household->payments->where('status', 'completed')->sum('amount'),
        ];

        return view('admin.pages.household-show', compact('household', 'tasks', 'renewals', 'documents', 'stats'));
    }
}
