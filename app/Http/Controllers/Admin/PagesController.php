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

        $data = $this->loadData($page);

        return view('admin.pages.' . $page, array_merge(
            ['active' => $page, 'pageTitle' => $this->title($page)],
            $data
        ));
    }

    /**
     * Detail / drill-down pages for entities that have a backing model.
     */
    protected array $detailMap = [
        'users'         => [User::class, []],
        'households'    => [Household::class, ['members', 'subscription.plan', 'tasks', 'documents', 'renewals', 'payments']],
        'invitations'   => [Invitation::class, ['household']],
        'tasks'         => [Task::class, ['household']],
        'renewals'      => [Renewal::class, ['household']],
        'documents'     => [Document::class, ['household', 'files']],
        'subscriptions' => [Subscription::class, ['plan', 'household', 'user']],
        'payments'      => [Payment::class, ['user', 'household', 'subscription']],
        'notifications' => [Notification::class, ['user']],
        'audit-logs'    => [ActivityLog::class, ['user', 'subject']],
    ];

    public function detail(Request $request, $page, $id)
    {
        abort_unless(array_key_exists($page, $this->detailMap), 404);

        [$model, $with] = $this->detailMap[$page];
        $record = $model::with($with)->findOrFail($id);

        return view('admin.pages.' . $page . '-show', [
            'active'   => $page,
            'pageTitle' => $this->title($page),
            'record'   => $record,
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

    protected function loadData($page): array
    {
        return match ($page) {
            'dashboard' => $this->dashboard(),
            'users' => ['users' => User::latest()->paginate(20)],
            'households' => ['households' => Household::withCount('members')->latest()->paginate(20)],
            'invitations' => ['invitations' => Invitation::with('household')->latest()->paginate(20)],
            'tasks' => ['tasks' => Task::with('household')->latest()->paginate(20)],
            'renewals' => ['renewals' => Renewal::with('household')->latest()->paginate(20)],
            'documents' => ['documents' => Document::with('household')->latest()->paginate(20)],
            'subscriptions' => ['subscriptions' => Subscription::with('plan', 'household')->latest()->paginate(20)],
            'payments' => ['payments' => Payment::with('user', 'household')->latest()->paginate(20)],
            'notifications' => ['notifications' => Notification::latest()->paginate(20)],
            'audit-logs' => ['logs' => ActivityLog::with('causer')->latest()->paginate(20)],
            'activity-map' => ['activities' => ActivityLog::with('causer')->latest()->take(50)->get()],
            default => [],
        };
    }

    protected function dashboard(): array
    {
        return [
            'totalUsers' => User::count(),
            'totalHouseholds' => Household::count(),
            'activeSubscriptions' => Subscription::where('status', 'active')->count(),
            'totalRevenue' => Payment::where('status', 'completed')->sum('amount'),
            'totalTasks' => Task::count(),
            'pendingTasks' => Task::where('status', '!=', 'completed')->count(),
            'totalRenewals' => Renewal::count(),
            'overdueRenewals' => Renewal::where('status', '!=', 'completed')->where('due_date', '<', now())->count(),
            'totalDocuments' => Document::count(),
            'openInvitations' => Invitation::whereNull('accepted_at')->where('expires_at', '>', now())->count(),
            'recentUsers' => User::latest()->take(5)->get(),
            'recentPayments' => Payment::with('user', 'household')->latest()->take(5)->get(),
        ];
    }
}
