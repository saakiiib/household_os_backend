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
                ->select('id', 'user_id', 'household_id', 'subscription_plan_id', 'status', 'billing_period', 'product_id', 'metadata', 'current_period_end'))
                ->addColumn('user_link', function ($s) {
                    if (!$s->user) return 'N/A';
                    return '<a href="' . route('admin.users.show', $s->user) . '" class="text-body">' . e($s->user->name) . '</a>';
                })
                ->addColumn('household_link', function ($s) {
                    if (!$s->household) return 'N/A';
                    return '<a href="' . route('admin.households.show', $s->household) . '" class="text-body">' . e($s->household->name) . '</a>';
                })
                ->addColumn('plan_name', fn($s) => $s->plan->name ?? 'N/A')
                ->addColumn('billing_label', fn($s) => $s->billing_period ? ucfirst($s->billing_period === 'annual' ? 'Annual' : $s->billing_period) : '-')
                ->addColumn('next_plan', function ($s) {
                    $metadata = is_array($s->metadata) ? $s->metadata : [];
                    $pendingPlan = $metadata['pending_plan'] ?? null;
                    $pendingBilling = $metadata['pending_billing_period'] ?? null;
                    if (!$pendingPlan && !$pendingBilling) return '-';
                    $plan = $pendingPlan ? ucfirst($pendingPlan) : 'Plan change';
                    $billing = $pendingBilling ? ' (' . ucfirst($pendingBilling === 'annual' ? 'Annual' : $pendingBilling) . ')' : '';
                    return '<span class="badge badge-soft-info">' . e($plan . $billing) . '</span>';
                })
                ->addColumn('status_badge', function ($s) {
                    $cls = match($s->status) { 'active' => 'success', 'trial' => 'info', 'expired' => 'danger', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($s->status) . '</span>';
                })
                ->addColumn('period_end_fmt', fn($s) => $s->current_period_end ? $s->current_period_end->format('d M Y H:i:s') : '-')
                ->addColumn('action', function ($s) {
                    return '<a href="' . route('admin.subscriptions.show', $s) . '" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>';
                })
                ->rawColumns(['user_link', 'household_link', 'next_plan', 'status_badge', 'action'])
                ->make(true);
        }

        $totalSubscriptions = \App\Models\Subscription::count();
        $activeSubscriptions = \App\Models\Subscription::where('status', 'active')->count();
        $trialSubscriptions = \App\Models\Subscription::where('status', 'trial')->count();
        $expiredSubscriptions = \App\Models\Subscription::where('status', 'expired')->count();

        return view('admin.pages.subscriptions', compact(
            'totalSubscriptions', 'activeSubscriptions', 'trialSubscriptions', 'expiredSubscriptions'
        ));
    }

    public function show(Subscription $subscription)
    {
        $subscription->load('user', 'household', 'plan', 'payments', 'transactions');

        return view('admin.pages.subscription-show', compact('subscription'));
    }
}
