<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Household;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Renewal;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PagesController extends Controller
{
    /**
     * All reference admin pages that must exist. Any other slug => 404.
     */
    protected array $pages = [
        'dashboard', 'ai-insights', 'system-status',
        'invitations',
        'ocr-queue', 'storage', 'automations',
        'revenue',
        'tickets', 'escalations', 'communications', 'notifications', 'templates',
        'website-cms', 'app-cms', 'blog', 'media',
        'audit-logs', 'devices', 'fraud', 'api-logs', 'recycle-bin',
        'analytics', 'reports', 'health-scores', 'activity-map',
        'feature-flags', 'backups', 'settings',
    ];

    public function show(Request $request, $page)
    {
        abort_unless(in_array($page, $this->pages, true), 404);

        if ($page === 'dashboard') {
            return redirect()->route('admin.dashboard');
        }

        if ($request->ajax() && $request->has('draw') && $this->isDataTablePage($page)) {
            return $this->dataTableResponse($page);
        }

        $data = $this->isDataTablePage($page)
            ? $this->loadStats($page)
            : $this->loadData($page, $request);

        $active = $page;
        $pageTitle = $this->title($page);

        return view('admin.pages.' . $page, array_merge(
            compact('active', 'pageTitle'),
            $data
        ));
    }

    protected function isDataTablePage(string $page): bool
    {
        return in_array($page, ['invitations', 'notifications', 'audit-logs'], true);
    }

    protected function loadStats(string $page): array
    {
        return match ($page) {
            'invitations' => [
                'totalInvitations' => Invitation::count(),
                'pendingInvitations' => Invitation::where('status', 'pending')->count(),
                'acceptedInvitations' => Invitation::where('status', 'accepted')->count(),
                'expiredInvitations' => Invitation::whereIn('status', ['expired', 'rejected', 'cancelled'])->count(),
            ],
            'notifications' => [
                'totalNotifications' => Notification::count(),
                'unreadNotifications' => Notification::whereNull('read_at')->count(),
                'readNotifications' => Notification::whereNotNull('read_at')->count(),
            ],
            'audit-logs' => [
                'totalLogs' => ActivityLog::count(),
                'todayLogs' => ActivityLog::whereDate('created_at', now()->toDateString())->count(),
            ],
            default => [],
        };
    }

    protected function dataTableResponse(string $page)
    {
        if ($page === 'invitations') {
            $query = Invitation::with('household');

            return DataTables::of($query)
                ->addColumn('invitee', fn($i) => e($i->invited_email))
                ->addColumn('household', fn($i) => $i->household ? e($i->household->name) : '—')
                ->addColumn('role', function ($i) {
                    $color = match ($i->role) {
                        'admin' => 'primary',
                        'co-admin' => 'info',
                        default => 'secondary',
                    };

                    return '<span class="badge bg-soft-' . $color . ' text-' . $color . ' text-capitalize">'
                        . str_replace('-', ' ', $i->role) . '</span>';
                })
                ->addColumn('invite_code', fn($i) => ($i->household && $i->household->invite_code)
                    ? '<code>' . e($i->household->invite_code) . '</code>'
                    : '—')
                ->addColumn('sent', fn($i) => $i->created_at->format('d M Y'))
                ->addColumn('status', function ($i) {
                    $color = match ($i->status) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'expired' => 'danger',
                        default => 'secondary',
                    };

                    return '<span class="badge bg-soft-' . $color . ' text-' . $color . '">' . ucfirst($i->status) . '</span>';
                })
                ->addColumn('action', fn($i) => '<a href="' . route('admin.page.detail', ['page' => 'invitations', 'id' => $i->id]) . '" class="btn btn-sm btn-soft-primary">View</a>')
                ->rawColumns(['role', 'invite_code', 'status', 'action'])
                ->make(true);
        }

        if ($page === 'notifications') {
            $query = Notification::with('user');

            return DataTables::of($query)
                ->addColumn('notification', function ($n) {
                    $title = e($n->title ?? '—');
                    $body = e(Str::limit($n->body ?? '', 60));

                    return '<div class="fw-medium">' . $title . '</div><div class="text-muted fs-13">' . $body . '</div>';
                })
                ->addColumn('type', fn($n) => '<span class="badge bg-soft-info text-info">' . ucfirst($n->type ?? 'general') . '</span>')
                ->addColumn('status', function ($n) {
                    return !empty($n->read_at)
                        ? '<span class="badge bg-soft-secondary text-secondary">Read</span>'
                        : '<span class="badge bg-soft-warning text-warning">Unread</span>';
                })
                ->addColumn('created', fn($n) => $n->created_at ? $n->created_at->format('d M Y H:i') : '—')
                ->addColumn('action', fn($n) => '<a href="' . route('admin.page.detail', ['page' => 'notifications', 'id' => $n->id]) . '" class="btn btn-sm btn-soft-primary">View</a>')
                ->rawColumns(['notification', 'type', 'status', 'action'])
                ->make(true);
        }

        $query = ActivityLog::with('causer');

        return DataTables::of($query)
            ->addColumn('time', fn($l) => $l->created_at ? $l->created_at->format('d M Y H:i') : '—')
            ->addColumn('actor', fn($l) => optional($l->causer)->name ?? 'System')
            ->addColumn('action_name', fn($l) => e($l->description ?? '—'))
            ->addColumn('target', fn($l) => class_basename($l->subject_type ?? '') . ' #' . ($l->subject_id ?? ''))
            ->addColumn('details', fn($l) => '<a href="' . route('admin.page.detail', ['page' => 'audit-logs', 'id' => $l->id]) . '" class="btn btn-sm btn-soft-primary">View</a>')
            ->rawColumns(['details'])
            ->make(true);
    }

    /**
     * Detail / drill-down pages for entities that have a backing model.
     * (The 7 core entities keep their existing detail screens via the old
     * admin.*.show routes; this covers the additional ones.)
     */
    protected array $detailMap = [
        'invitations'   => [Invitation::class, ['household']],
        'notifications' => [Notification::class, ['user']],
        'audit-logs'    => [ActivityLog::class, ['user', 'subject']],
    ];

    public function detail(Request $request, $page, $id)
    {
        abort_unless(array_key_exists($page, $this->detailMap), 404);

        [$model, $with] = $this->detailMap[$page];
        $record = $model::with($with)->findOrFail($id);

        $active = $page;
        $pageTitle = $this->title($page);

        return view('admin.pages.' . $page . '-show', compact('active', 'pageTitle', 'record'));
    }

    protected function title($page): string
    {
        return match ($page) {
            'ai-insights' => 'AI Insights',
            'system-status' => 'System Status',
            'ocr-queue' => 'OCR & Document Intelligence',
            'subscriptions' => 'Subscription Management',
            'payments' => 'Payment Management',
            'revenue' => 'Revenue Analytics',
            'tickets' => 'Customer Support Centre',
            'escalations' => 'Escalation Centre',
            'communications' => 'Communication Centre',
            'notifications' => 'Push Notification Management',
            'templates' => 'Message Templates',
            'website-cms' => 'Website Content Management',
            'app-cms' => 'Mobile App Content',
            'blog' => 'Blog Management',
            'media' => 'Media Library',
            'audit-logs' => 'Audit Logs',
            'devices' => 'Device Manager',
            'fraud' => 'Fraud & Abuse Detection',
            'api-logs' => 'API Access Logs',
            'recycle-bin' => 'Recycle Bin',
            'analytics' => 'Platform Analytics',
            'reports' => 'Reports & Exports',
            'health-scores' => 'Household Health Scores',
            'activity-map' => 'Live Activity',
            'feature-flags' => 'Feature Flags',
            'backups' => 'Backup Manager',
            'settings' => 'Platform Settings',
            default => ucfirst($page),
        };
    }

    protected function loadData($page, Request $request): array
    {
        return match ($page) {
            'users' => ['users' => User::latest()->paginate(20)],
            'households' => ['households' => Household::withCount('members')->latest()->paginate(20)],
            'household-details' => $this->householdDetails($request),
            'invitations' => ['invitations' => Invitation::with('household')->latest()->paginate(20)],
            'tasks' => ['tasks' => Task::with('household')->latest()->paginate(20)],
            'renewals' => ['renewals' => Renewal::with('household')->latest()->paginate(20)],
            'documents' => ['documents' => Document::with('household')->latest()->paginate(20)],
            'subscriptions' => ['subscriptions' => Subscription::with('plan', 'household')->latest()->paginate(20)],
            'payments' => ['payments' => Payment::with('user', 'household')->latest()->paginate(20)],
            'notifications' => ['notifications' => Notification::latest()->paginate(20)],
            'audit-logs' => ['logs' => ActivityLog::with('causer')->latest()->paginate(20)],
            'activity-map' => ['activities' => ActivityLog::with('causer')->latest()->take(50)->get()],
            'ai-insights' => $this->aiInsightsData(),
            'analytics' => $this->analyticsData(),
            'system-status' => $this->systemStatusData(),
            'health-scores' => $this->healthScoresData(),
            'revenue' => $this->revenueData(),
            'fraud' => $this->fraudData(),
            default => [],
        };
    }

    protected function dailySeries(callable $query, string $dateColumn = 'created_at'): array
    {
        $labels = [];
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $labels[] = $d->format('d M');
            $series[] = $query($d);
        }

        return ['labels' => $labels, 'series' => $series];
    }

    protected function recentFindings(int $take = 6): array
    {
        return ActivityLog::with('user')->latest()->take($take)->get()->map(
            fn($a) => [
                'title' => $a->description ?? '—',
                'detail' => class_basename($a->subject_type ?? '') . ' #' . ($a->subject_id ?? ''),
                'time' => $a->created_at->diffForHumans(),
                'level' => str_contains(strtolower((string) ($a->description ?? '')), 'fail')
                    || str_contains(strtolower((string) ($a->description ?? '')), 'error')
                    ? 'danger' : 'info',
            ]
        )->all();
    }

    protected function aiInsightsData(): array
    {
        $activeNow = User::count();
        $atRisk = Renewal::where('status', '!=', 'completed')
            ->where('due_date', '<', now()->toDateString())->count();
        $opportunities = Subscription::where('status', 'trial')->count();
        $alerts = ActivityLog::where('created_at', '>=', now()->subDay())->count();

        $kpis = [
            ['label' => 'Active Now', 'value' => number_format($activeNow), 'trend' => 3.2, 'icon' => 'ri-pulse-line'],
            ['label' => 'At Risk', 'value' => number_format($atRisk), 'trend' => -1.1, 'icon' => 'ri-alert-line'],
            ['label' => 'Opportunities', 'value' => number_format($opportunities), 'trend' => 5.4, 'icon' => 'ri-lightbulb-line'],
            ['label' => 'Alerts', 'value' => number_format($alerts), 'trend' => 0.0, 'icon' => 'ri-notification-3-line'],
        ];

        $trend = $this->dailySeries(
            fn($d) => ActivityLog::whereDate('created_at', $d->toDateString())->count()
        );

        return [
            'kpis' => $kpis,
            'trendLabels' => $trend['labels'],
            'trendSeries' => $trend['series'],
            'findings' => $this->recentFindings(),
        ];
    }

    protected function revenueData(): array
    {
        $totalRevenue = (float) Payment::where('status', 'succeeded')->sum('amount');
        $monthRevenue = (float) Payment::where('status', 'succeeded')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $activeSubs = Subscription::where('status', 'active')->with('plan')->get();
        $mrr = $activeSubs->sum(fn($s) => (float) ($s->plan->monthly_price ?? 0));

        $kpis = [
            ['label' => 'Total Revenue', 'value' => '£' . number_format($totalRevenue, 2), 'trend' => 4.8, 'icon' => 'ri-money-pound-circle-line'],
            ['label' => 'MRR', 'value' => '£' . number_format($mrr, 2), 'trend' => 6.1, 'icon' => 'ri-vip-crown-line'],
            ['label' => 'This Month', 'value' => '£' . number_format($monthRevenue, 2), 'trend' => 2.3, 'icon' => 'ri-calendar-line'],
        ];

        $trend = $this->dailySeries(
            fn($d) => (float) Payment::where('status', 'succeeded')
                ->whereDate('created_at', $d->toDateString())->sum('amount')
        );

        $findings = Payment::with('user', 'household')->where('status', 'succeeded')
            ->latest()->take(6)->get()->map(
                fn($p) => [
                    'title' => 'Payment ' . ($p->gateway_payment_id ?: '#' . $p->id),
                    'detail' => ($p->user?->name ?: 'User') . ' · £' . number_format($p->amount, 2)
                        . ' · ' . ($p->gateway ?? 'card'),
                    'time' => $p->created_at->diffForHumans(),
                    'level' => 'info',
                ]
            )->all();

        return [
            'kpis' => $kpis,
            'trendLabels' => $trend['labels'],
            'trendSeries' => $trend['series'],
            'findings' => $findings,
        ];
    }

    protected function fraudData(): array
    {
        $overdue = Renewal::where('status', '!=', 'completed')
            ->where('due_date', '<', now()->toDateString())->count();
        $failed = Payment::where('status', 'failed')->count();
        $cancelled = Subscription::where('status', 'cancelled')->count();

        $kpis = [
            ['label' => 'Overdue Renewals', 'value' => number_format($overdue), 'trend' => 1.4, 'icon' => 'ri-refresh-line'],
            ['label' => 'Failed Payments', 'value' => number_format($failed), 'trend' => -0.5, 'icon' => 'ri-close-circle-line'],
            ['label' => 'Cancelled Subs', 'value' => number_format($cancelled), 'trend' => -1.8, 'icon' => 'ri-close-circle-line'],
        ];

        $trend = $this->dailySeries(
            fn($d) => Payment::where('status', 'failed')
                ->whereDate('created_at', $d->toDateString())->count()
        );

        $findings = Renewal::with('household')
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', now()->toDateString())
            ->latest()->take(6)->get()->map(
                fn($r) => [
                    'title' => 'Overdue renewal #' . $r->id,
                    'detail' => ($r->household?->name ?: 'Household') . ' · due '
                        . ($r->due_date ? $r->due_date->format('d M Y') : '—'),
                    'time' => $r->created_at->diffForHumans(),
                    'level' => 'danger',
                ]
            )->all();

        return [
            'kpis' => $kpis,
            'trendLabels' => $trend['labels'],
            'trendSeries' => $trend['series'],
            'findings' => $findings,
        ];
    }

    protected function systemStatusData(): array
    {
        $dbOk = true;
        try {
            \DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $stripeOk = !empty(config('services.stripe.secret'));
        $paypalOk = !empty(config('services.paypal.client_id'));
        $mailOk = !empty(config('mail.mailers.smtp.host'))
            && !empty(config('mail.mailers.smtp.username'))
            && !empty(config('mail.mailers.smtp.password'));

        $kpis = [
            ['label' => 'API', 'value' => 'Operational', 'status' => 'ok', 'icon' => 'ri-server-line'],
            ['label' => 'Database', 'value' => $dbOk ? 'Connected' : 'Down', 'status' => $dbOk ? 'ok' : 'warning', 'icon' => 'ri-database-2-line'],
            ['label' => 'Stripe', 'value' => $stripeOk ? 'Connected' : 'Not configured', 'status' => $stripeOk ? 'ok' : 'warning', 'icon' => 'ri-visa-line'],
            ['label' => 'PayPal', 'value' => $paypalOk ? 'Connected' : 'Not configured', 'status' => $paypalOk ? 'ok' : 'warning', 'icon' => 'ri-paypal-line'],
            ['label' => 'Email', 'value' => $mailOk ? 'Configured' : 'Not configured', 'status' => $mailOk ? 'ok' : 'warning', 'icon' => 'ri-mail-line'],
        ];

        $trend = $this->dailySeries(
            fn($d) => ActivityLog::whereDate('created_at', $d->toDateString())->count()
        );

        $findings = $this->recentFindings();

        return [
            'kpis' => $kpis,
            'trendLabels' => $trend['labels'],
            'trendSeries' => $trend['series'],
            'findings' => $findings,
        ];
    }

    protected function analyticsData(): array
    {
        $newUsers = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $newHouseholds = Household::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $activeSubs = Subscription::where('status', 'active')->count();
        $totalUsers = User::count();

        $kpis = [
            ['label' => 'New Users (mo)', 'value' => number_format($newUsers), 'trend' => 5.1, 'icon' => 'ri-user-line'],
            ['label' => 'New Households (mo)', 'value' => number_format($newHouseholds), 'trend' => 3.3, 'icon' => 'ri-home-line'],
            ['label' => 'Active Subscriptions', 'value' => number_format($activeSubs), 'trend' => 6.7, 'icon' => 'ri-vip-crown-line'],
            ['label' => 'Total Users', 'value' => number_format($totalUsers), 'trend' => 4.0, 'icon' => 'ri-community-line'],
        ];

        $trend = $this->dailySeries(
            fn($d) => User::whereDate('created_at', $d->toDateString())->count()
        );

        $findings = $this->recentFindings();

        return [
            'kpis' => $kpis,
            'trendLabels' => $trend['labels'],
            'trendSeries' => $trend['series'],
            'findings' => $findings,
        ];
    }

    protected function healthScoresData(): array
    {
        $total = Household::count();
        $overdue = Renewal::where('status', '!=', 'completed')
            ->where('due_date', '<', now()->toDateString())->count();
        $trials = Subscription::where('status', 'trial')->count();
        $active = Subscription::where('status', 'active')->count();

        $kpis = [
            ['label' => 'Total Households', 'value' => number_format($total), 'trend' => 3.0, 'icon' => 'ri-home-line'],
            ['label' => 'Overdue Renewals', 'value' => number_format($overdue), 'trend' => -1.1, 'icon' => 'ri-refresh-line'],
            ['label' => 'Active Subs', 'value' => number_format($active), 'trend' => 6.7, 'icon' => 'ri-vip-crown-line'],
            ['label' => 'Trials', 'value' => number_format($trials), 'trend' => 5.4, 'icon' => 'ri-rocket-line'],
        ];

        $trend = $this->dailySeries(
            fn($d) => Household::whereDate('created_at', $d->toDateString())->count()
        );

        $findings = Household::withCount('members')
            ->latest()->take(6)->get()->map(
                fn($h) => [
                    'title' => $h->name ?: 'Household #' . $h->id,
                    'detail' => $h->members_count . ' member(s)',
                    'time' => $h->created_at->diffForHumans(),
                    'level' => 'info',
                ]
            )->all();

        return [
            'kpis' => $kpis,
            'trendLabels' => $trend['labels'],
            'trendSeries' => $trend['series'],
            'findings' => $findings,
        ];
    }

    protected function householdDetails(Request $request): array
    {
        $id = $request->get('id');
        $household = $id
            ? Household::with('members.user')->find($id)
            : Household::with('members.user')->first();

        if (!$household) {
            return ['household' => null, 'members' => collect(), 'payments' => collect(), 'timeline' => collect()];
        }

        $members = $household->members()->with('user')->get();
        $payments = Payment::where('household_id', $household->id)->latest()->take(10)->get();
        $timeline = ActivityLog::where('household_id', $household->id)->latest()->take(10)->get();

        return compact('household', 'members', 'payments', 'timeline');
    }
}
