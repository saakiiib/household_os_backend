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
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    /**
     * All reference admin pages that must exist. Any other slug => 404.
     */
    protected array $pages = [
        'dashboard', 'ai-insights', 'system-status',
        'users', 'households', 'household-details', 'invitations', 'admin-roles',
        'tasks', 'renewals', 'documents', 'ocr-queue', 'storage', 'automations',
        'subscriptions', 'payments', 'refunds', 'coupons', 'revenue',
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

        $data = $this->loadData($page, $request);

        return view('admin.pages.' . $page, array_merge(
            ['active' => $page, 'pageTitle' => $this->title($page)],
            $data
        ));
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

        return view('admin.pages.' . $page . '-show', [
            'active'    => $page,
            'pageTitle' => $this->title($page),
            'record'    => $record,
        ]);
    }

    protected function title($page): string
    {
        return match ($page) {
            'ai-insights' => 'AI Insights',
            'system-status' => 'System Status',
            'admin-roles' => 'Admin Roles & Permissions',
            'ocr-queue' => 'OCR & Document Intelligence',
            'subscriptions' => 'Subscription Management',
            'payments' => 'Payment Management',
            'refunds' => 'Refund Management',
            'coupons' => 'Coupons & Promotions',
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
            'ai-insights' => $this->insightData(),
            'analytics' => $this->insightData(),
            'system-status' => $this->insightData(),
            'health-scores' => $this->insightData(),
            'revenue' => $this->insightData(),
            'fraud' => $this->insightData(),
            default => [],
        };
    }

    protected function insightData(): array
    {
        $activeNow = User::count();
        $atRisk = Renewal::where('status', 'overdue')->count();
        $opportunities = Subscription::where('status', 'trial')->count();
        $alerts = ActivityLog::where('created_at', '>=', now()->subDay())->count();

        $kpis = [
            ['label' => 'Active Now', 'value' => number_format($activeNow), 'trend' => 3.2, 'icon' => 'ri-pulse-line'],
            ['label' => 'At Risk', 'value' => number_format($atRisk), 'trend' => -1.1, 'icon' => 'ri-alert-line'],
            ['label' => 'Opportunities', 'value' => number_format($opportunities), 'trend' => 5.4, 'icon' => 'ri-lightbulb-line'],
            ['label' => 'Alerts', 'value' => number_format($alerts), 'trend' => 0.0, 'icon' => 'ri-notification-3-line'],
        ];

        $trendLabels = [];
        $trendSeries = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $trendLabels[] = $d->format('d M');
            $trendSeries[] = ActivityLog::whereDate('created_at', $d->toDateString())->count();
        }

        $findings = ActivityLog::with('user')->latest()->take(6)->get()->map(
            fn($a) => [
                'title' => $a->description ?? '—',
                'detail' => class_basename($a->subject_type ?? '') . ' #' . ($a->subject_id ?? ''),
                'time' => $a->created_at->diffForHumans(),
                'level' => 'info',
            ]
        )->all();

        return compact('kpis', 'trendLabels', 'trendSeries', 'findings');
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
