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
                ->addColumn('creator_name', fn($h) => $h->creator->name ?? 'N/A')
                ->addColumn('members_count', fn($h) => $h->members->count())
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
                ->rawColumns(['subscription_status', 'action'])
                ->make(true);
        }

        return view('admin.pages.households');
    }

    public function show(Household $household)
    {
        $household->load('creator', 'members', 'subscription', 'payments', 'invitations');
        return view('admin.pages.household-show', compact('household'));
    }
}
