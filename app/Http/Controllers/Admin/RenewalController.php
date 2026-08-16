<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renewal;

class RenewalController extends Controller
{
    public function index()
    {
        $totalRenewals = \App\Models\Renewal::count();
        $pendingRenewals = \App\Models\Renewal::where('status', 'pending')->count();
        $completedRenewals = \App\Models\Renewal::where('status', 'completed')->count();
        $renewalsValue = (float) \App\Models\Renewal::sum('amount');

        return view('admin.pages.renewals', compact(
            'totalRenewals', 'pendingRenewals', 'completedRenewals', 'renewalsValue'
        ));
    }

    public function show(Renewal $renewal)
    {
        $renewal->load('household', 'createdBy', 'vehicle', 'parent', 'children.createdBy');

        $siblings = Renewal::where('household_id', $renewal->household_id)
            ->where('id', '!=', $renewal->id)
            ->latest()
            ->take(10)
            ->get();

        return view('admin.pages.renewal-show', compact('renewal', 'siblings'));
    }
}
