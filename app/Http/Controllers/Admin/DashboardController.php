<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Household;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Renewal;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $totalHouseholds = Household::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $totalTasks = Task::count();
        $pendingTasks = Task::where('status', '!=', 'completed')->count();
        $totalRenewals = Renewal::count();
        $overdueRenewals = Renewal::where('status', '!=', 'completed')
            ->where('due_date', '<', now())->count();

        $recentUsers = User::latest()->take(5)->get();
        $recentPayments = Payment::with('user', 'household')->latest()->take(5)->get();

        return view('admin.pages.dashboard', compact(
            'totalUsers', 'totalHouseholds', 'activeSubscriptions',
            'totalRevenue', 'totalTasks', 'pendingTasks',
            'totalRenewals', 'overdueRenewals',
            'recentUsers', 'recentPayments'
        ));
    }
}
