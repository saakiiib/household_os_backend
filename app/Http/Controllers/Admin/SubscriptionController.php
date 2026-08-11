<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Yajra\DataTables\Facades\DataTables;

class SubscriptionController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(Subscription::with('user', 'household', 'plan')
                ->select('id', 'user_id', 'household_id', 'subscription_plan_id', 'status', 'current_period_end'))
                ->addColumn('user_name', fn($s) => $s->user->name ?? 'N/A')
                ->addColumn('household_name', fn($s) => $s->household->name ?? 'N/A')
                ->addColumn('plan_name', fn($s) => $s->plan->name ?? 'N/A')
                ->addColumn('status_badge', function ($s) {
                    $cls = match($s->status) { 'active' => 'success', 'trial' => 'info', 'expired' => 'danger', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($s->status) . '</span>';
                })
                ->addColumn('period_end_fmt', fn($s) => $s->current_period_end ? $s->current_period_end->format('d M Y') : '-')
                ->rawColumns(['status_badge'])
                ->make(true);
        }

        return view('admin.pages.subscriptions');
    }
}
