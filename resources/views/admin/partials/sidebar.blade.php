<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
            <span class="logo-lg"><b>Household OS</b></span>
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
            <span class="logo-lg"><b>Household OS</b></span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                @php
                    $routeName = Route::currentRouteName();
                    $cur = match (true) {
                        str_starts_with($routeName, 'admin.users') => 'users',
                        str_starts_with($routeName, 'admin.households') => 'households',
                        str_starts_with($routeName, 'admin.tasks') => 'tasks',
                        str_starts_with($routeName, 'admin.renewals') => 'renewals',
                        str_starts_with($routeName, 'admin.documents') => 'documents',
                        str_starts_with($routeName, 'admin.subscriptions') => 'subscriptions',
                        str_starts_with($routeName, 'admin.admins') => 'admins',
                        str_starts_with($routeName, 'admin.payments') => 'payments',
                        default => ($active ?? request()->route('page') ?? ''),
                    };
                @endphp
                <li class="menu-title" style="text-align: center;">Overview</li>
                <li class="nav-item"><a href="{{ route('admin.dashboard') }}" class="nav-link {{ $cur === 'dashboard' ? 'active' : '' }}"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'ai-insights']) }}" class="nav-link {{ $cur === 'ai-insights' ? 'active' : '' }}"><i class="ri-robot-line"></i><span>AI Insights</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'system-status']) }}" class="nav-link {{ $cur === 'system-status' ? 'active' : '' }}"><i class="ri-server-line"></i><span>System Status</span></a></li>

                <li class="menu-title" style="text-align: center;">People</li>
                <li class="nav-item"><a href="{{ route('admin.users.index') }}" class="nav-link {{ $cur === 'users' ? 'active' : '' }}"><i class="ri-user-line"></i><span>Users</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.households.index') }}" class="nav-link {{ $cur === 'households' ? 'active' : '' }}"><i class="ri-home-line"></i><span>Households</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'invitations']) }}" class="nav-link {{ $cur === 'invitations' ? 'active' : '' }}"><i class="ri-mail-send-line"></i><span>Invitations</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.admins.index') }}" class="nav-link {{ $cur === 'admins' ? 'active' : '' }}"><i class="ri-shield-user-line"></i><span>Admins</span></a></li>

                <li class="menu-title" style="text-align: center;">Operations</li>
                <li class="nav-item"><a href="{{ route('admin.tasks.index') }}" class="nav-link {{ $cur === 'tasks' ? 'active' : '' }}"><i class="ri-task-line"></i><span>Tasks</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.renewals.index') }}" class="nav-link {{ $cur === 'renewals' ? 'active' : '' }}"><i class="ri-refresh-line"></i><span>Renewals</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.documents.index') }}" class="nav-link {{ $cur === 'documents' ? 'active' : '' }}"><i class="ri-file-text-line"></i><span>Documents</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'ocr-queue']) }}" class="nav-link {{ $cur === 'ocr-queue' ? 'active' : '' }}"><i class="ri-scan-line"></i><span>OCR Queue</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'storage']) }}" class="nav-link {{ $cur === 'storage' ? 'active' : '' }}"><i class="ri-hard-drive-2-line"></i><span>Storage Explorer</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'automations']) }}" class="nav-link {{ $cur === 'automations' ? 'active' : '' }}"><i class="ri-flashlight-line"></i><span>Automations</span></a></li>

                <li class="menu-title" style="text-align: center;">Billing</li>
                <li class="nav-item"><a href="{{ route('admin.subscriptions.index') }}" class="nav-link {{ $cur === 'subscriptions' ? 'active' : '' }}"><i class="ri-star-line"></i><span>Subscriptions</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.payments.index') }}" class="nav-link {{ $cur === 'payments' ? 'active' : '' }}"><i class="ri-money-dollar-circle-line"></i><span>Payments</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'revenue']) }}" class="nav-link {{ $cur === 'revenue' ? 'active' : '' }}"><i class="ri-line-chart-line"></i><span>Revenue Analytics</span></a></li>

                <li class="menu-title" style="text-align: center;">Support &amp; Comms</li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'tickets']) }}" class="nav-link {{ $cur === 'tickets' ? 'active' : '' }}"><i class="ri-customer-service-2-line"></i><span>Support Tickets</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'escalations']) }}" class="nav-link {{ $cur === 'escalations' ? 'active' : '' }}"><i class="ri-alarm-warning-line"></i><span>Escalations</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'communications']) }}" class="nav-link {{ $cur === 'communications' ? 'active' : '' }}"><i class="ri-chat-3-line"></i><span>Communication Centre</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'notifications']) }}" class="nav-link {{ $cur === 'notifications' ? 'active' : '' }}"><i class="ri-notification-3-line"></i><span>Push Notifications</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'templates']) }}" class="nav-link {{ $cur === 'templates' ? 'active' : '' }}"><i class="ri-file-text-line"></i><span>Message Templates</span></a></li>

                <li class="menu-title" style="text-align: center;">Content</li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'website-cms']) }}" class="nav-link {{ $cur === 'website-cms' ? 'active' : '' }}"><i class="ri-global-line"></i><span>Website CMS</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'app-cms']) }}" class="nav-link {{ $cur === 'app-cms' ? 'active' : '' }}"><i class="ri-smartphone-line"></i><span>Mobile App CMS</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'blog']) }}" class="nav-link {{ $cur === 'blog' ? 'active' : '' }}"><i class="ri-article-line"></i><span>Blog</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'media']) }}" class="nav-link {{ $cur === 'media' ? 'active' : '' }}"><i class="ri-image-line"></i><span>Media Library</span></a></li>

                <li class="menu-title" style="text-align: center;">Security</li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'audit-logs']) }}" class="nav-link {{ $cur === 'audit-logs' ? 'active' : '' }}"><i class="ri-list-check-2"></i><span>Audit Logs</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'devices']) }}" class="nav-link {{ $cur === 'devices' ? 'active' : '' }}"><i class="ri-device-line"></i><span>Device Manager</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'fraud']) }}" class="nav-link {{ $cur === 'fraud' ? 'active' : '' }}"><i class="ri-spy-line"></i><span>Fraud Detection</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'api-logs']) }}" class="nav-link {{ $cur === 'api-logs' ? 'active' : '' }}"><i class="ri-code-line"></i><span>API Access</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'recycle-bin']) }}" class="nav-link {{ $cur === 'recycle-bin' ? 'active' : '' }}"><i class="ri-delete-bin-line"></i><span>Recycle Bin</span></a></li>

                <li class="menu-title" style="text-align: center;">Insights</li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'analytics']) }}" class="nav-link {{ $cur === 'analytics' ? 'active' : '' }}"><i class="ri-pie-chart-2-line"></i><span>Platform Analytics</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'reports']) }}" class="nav-link {{ $cur === 'reports' ? 'active' : '' }}"><i class="ri-file-chart-line"></i><span>Reports</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'health-scores']) }}" class="nav-link {{ $cur === 'health-scores' ? 'active' : '' }}"><i class="ri-heart-pulse-line"></i><span>Health Scores</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'activity-map']) }}" class="nav-link {{ $cur === 'activity-map' ? 'active' : '' }}"><i class="ri-map-pin-line"></i><span>Live Activity</span></a></li>

                <li class="menu-title" style="text-align: center;">Platform</li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'feature-flags']) }}" class="nav-link {{ $cur === 'feature-flags' ? 'active' : '' }}"><i class="ri-flag-line"></i><span>Feature Flags</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'backups']) }}" class="nav-link {{ $cur === 'backups' ? 'active' : '' }}"><i class="ri-archive-line"></i><span>Backup Manager</span></a></li>
                <li class="nav-item"><a href="{{ route('admin.page', ['page' => 'settings']) }}" class="nav-link {{ $cur === 'settings' ? 'active' : '' }}"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
            </ul>
        </div>
    </div>
</div>
