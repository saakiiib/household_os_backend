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
                ->addColumn('user_link', function ($s) {
                    if (!$s->user) return 'N/A';
                    return '<a href="' . route('admin.users.show', $s->user) . '" class="text-body">' . e($s->user->name) . '</a>';
                })
                ->addColumn('household_link', function ($s) {
                    if (!$s->household) return 'N/A';
                    return '<a href="' . route('admin.households.show', $s->household) . '" class="text-body">' . e($s->household->name) . '</a>';
                })
                ->addColumn('plan_name', fn($s) => $s->plan->name ?? 'N/A')
                ->addColumn('status_badge', function ($s) {
                    $cls = match($s->status) { 'active' => 'success', 'trial' => 'info', 'expired' => 'danger', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($s->status) . '</span>';
                })
                ->addColumn('period_end_fmt', fn($s) => $s->current_period_end ? $s->current_period_end->format('d M Y') : '-')
                ->rawColumns(['user_link', 'household_link', 'status_badge'])
                ->make(true);
        }

        return view('admin.pages.subscriptions');
    }

    public function show(Subscription $subscription)
    {
        $subscription->load('user', 'household', 'plan', 'payments');

        return view('admin.pages.subscription-show', compact('subscription'));
    }
}
