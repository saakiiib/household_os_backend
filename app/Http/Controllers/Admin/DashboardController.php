<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Household;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Renewal;
use App\Models\Document;
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

        $trend = [
            'households' => $this->monthOverMonth(Household::class),
            'users'      => $this->monthOverMonth(User::class),
            'revenue'    => $this->revenueTrend(),
            'subscriptions' => $this->monthOverMonth(Subscription::class),
            'documents'  => $this->monthOverMonth(Document::class),
            'tasks'      => $this->monthOverMonth(Task::class),
            'renewals'   => $this->monthOverMonth(Renewal::class),
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

        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $stripeOk = !empty(config('services.stripe.secret'));
        $paypalOk = !empty(config('services.paypal.client_id'));
        $mailOk = !empty(config('mail.mailers.smtp.host'))
            && !empty(config('mail.mailers.smtp.username'))
            && !empty(config('mail.mailers.smtp.password'));

        $health = [
            ['label' => 'API', 'value' => 'Operational', 'status' => 'ok'],
            ['label' => 'Database', 'value' => $dbOk ? 'Healthy' : 'Down', 'status' => $dbOk ? 'ok' : 'warning'],
            ['label' => 'Stripe', 'value' => $stripeOk ? 'Connected' : 'Not configured', 'status' => $stripeOk ? 'ok' : 'warning'],
            ['label' => 'PayPal', 'value' => $paypalOk ? 'Connected' : 'Not configured', 'status' => $paypalOk ? 'ok' : 'warning'],
            ['label' => 'Email', 'value' => $mailOk ? 'Configured' : 'Not configured', 'status' => $mailOk ? 'ok' : 'warning'],
        ];

        return view('admin.pages.dashboard', compact(
            'totalHouseholds', 'totalUsers', 'activeSubscriptions', 'monthlyRevenue',
            'totalRevenue', 'totalDocuments', 'tasksToday', 'renewalsDue',
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
