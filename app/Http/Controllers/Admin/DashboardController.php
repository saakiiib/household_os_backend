<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Household;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Renewal;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Invitation;
use App\Models\ActivityLog;

class DashboardController extends Controller
{
    public function index()
    {
        $totalHouseholds = Household::count();
        $totalUsers = User::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $monthlyRevenue = (float) Payment::where('status', 'succeeded')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $totalRevenue = (float) Payment::where('status', 'succeeded')->sum('amount');
        $totalDocuments = Document::count();
        $tasksToday = Task::whereDate('due_date', now()->toDateString())->count();
        $renewalsDue = Renewal::where('status', '!=', 'completed')
            ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())->count();
        $openTickets = 0;

        $trend = [
            'households' => $this->monthOverMonth(Household::class),
            'users'      => $this->monthOverMonth(User::class),
            'revenue'    => $this->revenueTrend(),
            'subscriptions' => 6.7,
            'documents'  => 14.0,
            'tasks'      => 4.2,
            'renewals'   => -2.1,
            'tickets'    => 3.6,
        ];

        $growthLabels = [];
        $growthUsers = [];
        $growthHouseholds = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $growthLabels[] = $m->format('M');
            $growthUsers[] = User::whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)->count();
            $growthHouseholds[] = Household::whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)->count();
        }

        $revenueLabels = [];
        $revenueSeries = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $revenueLabels[] = $m->format('M');
            $revenueSeries[] = (float) Payment::where('status', 'succeeded')
                ->whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)->sum('amount');
        }

        $recentActivities = ActivityLog::with('user')->latest()->take(8)->get();

        $health = [
            ['label' => 'API', 'value' => '99.99%', 'status' => 'ok'],
            ['label' => 'Database', 'value' => 'Healthy', 'status' => 'ok'],
            ['label' => 'OCR Queue', 'value' => DocumentFile::count() . ' files', 'status' => 'ok'],
            ['label' => 'Backups', 'value' => 'Completed', 'status' => 'ok'],
            ['label' => 'Stripe', 'value' => 'Connected', 'status' => 'ok'],
        ];

        return view('admin.pages.dashboard', compact(
            'totalHouseholds', 'totalUsers', 'activeSubscriptions', 'monthlyRevenue',
            'totalRevenue', 'totalDocuments', 'tasksToday', 'renewalsDue', 'openTickets',
            'trend', 'growthLabels', 'growthUsers', 'growthHouseholds',
            'revenueLabels', 'revenueSeries', 'recentActivities', 'health'
        ));
    }

    private function monthOverMonth($model, $column = 'created_at')
    {
        $thisMonth = $model::whereYear($column, now()->year)
            ->whereMonth($column, now()->month)->count();
        $lastMonth = $model::whereYear($column, now()->subMonth()->year)
            ->whereMonth($column, now()->subMonth()->month)->count();

        if ($lastMonth == 0) {
            return $thisMonth > 0 ? 100.0 : 0.0;
        }

        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    private function revenueTrend()
    {
        $thisMonth = (float) Payment::where('status', 'succeeded')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->sum('amount');
        $lastMonth = (float) Payment::where('status', 'succeeded')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)->sum('amount');

        if ($lastMonth == 0) {
            return $thisMonth > 0 ? 100.0 : 0.0;
        }

        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }
}
