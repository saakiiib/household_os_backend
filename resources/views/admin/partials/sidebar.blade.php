<style>
    .menu-section-header .nav-link {
        padding: 10px 16px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #74788c !important;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .menu-section-header .nav-link:hover {
        color: #74788c !important;
        background: transparent;
    }
    .menu-section-header .section-arrow {
        font-size: 14px;
        width: 20px;
        text-align: center;
        transition: transform 0.2s;
    }
    .menu-section-header.section-closed .section-arrow {
        transform: rotate(-90deg);
    }
    .menu-section-header.section-open .section-arrow {
        transform: rotate(0deg);
    }
    .menu-section-item .nav-link i {
        width: 20px;
        text-align: center;
    }
</style>

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
                        $routeName === 'admin.dashboard' => 'dashboard',
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

                    $sections = [
                        'overview' => [
                            'label' => 'Overview',
                            'items' => [
                                ['route' => 'admin.dashboard', 'param' => null, 'key' => 'dashboard', 'icon' => 'ri-dashboard-line', 'label' => 'Dashboard'],
                                ['route' => 'admin.page', 'param' => 'ai-insights', 'key' => 'ai-insights', 'icon' => 'ri-robot-line', 'label' => 'AI Insights'],
                                ['route' => 'admin.page', 'param' => 'system-status', 'key' => 'system-status', 'icon' => 'ri-server-line', 'label' => 'System Status'],
                            ],
                        ],
                        'people' => [
                            'label' => 'People',
                            'items' => [
                                ['route' => 'admin.users.index', 'param' => null, 'key' => 'users', 'icon' => 'ri-user-line', 'label' => 'Users'],
                                ['route' => 'admin.households.index', 'param' => null, 'key' => 'households', 'icon' => 'ri-home-line', 'label' => 'Households'],
                                ['route' => 'admin.page', 'param' => 'invitations', 'key' => 'invitations', 'icon' => 'ri-mail-send-line', 'label' => 'Invitations'],
                                ['route' => 'admin.admins.index', 'param' => null, 'key' => 'admins', 'icon' => 'ri-shield-user-line', 'label' => 'Admins'],
                            ],
                        ],
                        'operations' => [
                            'label' => 'Operations',
                            'items' => [
                                ['route' => 'admin.tasks.index', 'param' => null, 'key' => 'tasks', 'icon' => 'ri-task-line', 'label' => 'Tasks'],
                                ['route' => 'admin.renewals.index', 'param' => null, 'key' => 'renewals', 'icon' => 'ri-refresh-line', 'label' => 'Renewals'],
                                ['route' => 'admin.documents.index', 'param' => null, 'key' => 'documents', 'icon' => 'ri-file-text-line', 'label' => 'Documents'],
                                ['route' => 'admin.page', 'param' => 'ocr-queue', 'key' => 'ocr-queue', 'icon' => 'ri-scan-line', 'label' => 'OCR Queue'],
                                ['route' => 'admin.page', 'param' => 'storage', 'key' => 'storage', 'icon' => 'ri-hard-drive-2-line', 'label' => 'Storage Explorer'],
                                ['route' => 'admin.page', 'param' => 'automations', 'key' => 'automations', 'icon' => 'ri-flashlight-line', 'label' => 'Automations'],
                            ],
                        ],
                        'billing' => [
                            'label' => 'Billing',
                            'items' => [
                                ['route' => 'admin.subscriptions.index', 'param' => null, 'key' => 'subscriptions', 'icon' => 'ri-star-line', 'label' => 'Subscriptions'],
                                ['route' => 'admin.payments.index', 'param' => null, 'key' => 'payments', 'icon' => 'ri-money-pound-circle-line', 'label' => 'Payments'],
                                ['route' => 'admin.page', 'param' => 'revenue', 'key' => 'revenue', 'icon' => 'ri-line-chart-line', 'label' => 'Revenue Analytics'],
                            ],
                        ],
                        'support' => [
                            'label' => 'Support & Comms',
                            'items' => [
                                ['route' => 'admin.page', 'param' => 'tickets', 'key' => 'tickets', 'icon' => 'ri-customer-service-2-line', 'label' => 'Support Tickets'],
                                ['route' => 'admin.page', 'param' => 'escalations', 'key' => 'escalations', 'icon' => 'ri-alarm-warning-line', 'label' => 'Escalations'],
                                ['route' => 'admin.page', 'param' => 'communications', 'key' => 'communications', 'icon' => 'ri-chat-3-line', 'label' => 'Communication Centre'],
                                ['route' => 'admin.page', 'param' => 'notifications', 'key' => 'notifications', 'icon' => 'ri-notification-3-line', 'label' => 'Push Notifications'],
                                ['route' => 'admin.page', 'param' => 'templates', 'key' => 'templates', 'icon' => 'ri-file-text-line', 'label' => 'Message Templates'],
                            ],
                        ],
                        'content' => [
                            'label' => 'Content',
                            'items' => [
                                ['route' => 'admin.page', 'param' => 'website-cms', 'key' => 'website-cms', 'icon' => 'ri-global-line', 'label' => 'Website CMS'],
                                ['route' => 'admin.page', 'param' => 'app-cms', 'key' => 'app-cms', 'icon' => 'ri-smartphone-line', 'label' => 'Mobile App CMS'],
                                ['route' => 'admin.page', 'param' => 'blog', 'key' => 'blog', 'icon' => 'ri-article-line', 'label' => 'Blog'],
                                ['route' => 'admin.page', 'param' => 'media', 'key' => 'media', 'icon' => 'ri-image-line', 'label' => 'Media Library'],
                            ],
                        ],
                        'security' => [
                            'label' => 'Security',
                            'items' => [
                                ['route' => 'admin.page', 'param' => 'audit-logs', 'key' => 'audit-logs', 'icon' => 'ri-list-check-2', 'label' => 'Audit Logs'],
                                ['route' => 'admin.page', 'param' => 'devices', 'key' => 'devices', 'icon' => 'ri-device-line', 'label' => 'Device Manager'],
                                ['route' => 'admin.page', 'param' => 'fraud', 'key' => 'fraud', 'icon' => 'ri-spy-line', 'label' => 'Fraud Detection'],
                                ['route' => 'admin.page', 'param' => 'api-logs', 'key' => 'api-logs', 'icon' => 'ri-code-line', 'label' => 'API Access'],
                                ['route' => 'admin.page', 'param' => 'recycle-bin', 'key' => 'recycle-bin', 'icon' => 'ri-delete-bin-line', 'label' => 'Recycle Bin'],
                            ],
                        ],
                        'insights' => [
                            'label' => 'Insights',
                            'items' => [
                                ['route' => 'admin.page', 'param' => 'analytics', 'key' => 'analytics', 'icon' => 'ri-pie-chart-2-line', 'label' => 'Platform Analytics'],
                                ['route' => 'admin.page', 'param' => 'reports', 'key' => 'reports', 'icon' => 'ri-file-chart-line', 'label' => 'Reports'],
                                ['route' => 'admin.page', 'param' => 'health-scores', 'key' => 'health-scores', 'icon' => 'ri-heart-pulse-line', 'label' => 'Health Scores'],
                                ['route' => 'admin.page', 'param' => 'activity-map', 'key' => 'activity-map', 'icon' => 'ri-map-pin-line', 'label' => 'Live Activity'],
                            ],
                        ],
                        'platform' => [
                            'label' => 'Platform',
                            'items' => [
                                ['route' => 'admin.page', 'param' => 'feature-flags', 'key' => 'feature-flags', 'icon' => 'ri-flag-line', 'label' => 'Feature Flags'],
                                ['route' => 'admin.page', 'param' => 'backups', 'key' => 'backups', 'icon' => 'ri-archive-line', 'label' => 'Backup Manager'],
                                ['route' => 'admin.page', 'param' => 'settings', 'key' => 'settings', 'icon' => 'ri-settings-3-line', 'label' => 'Settings'],
                            ],
                        ],
                    ];
                @endphp

                @foreach($sections as $sectionKey => $section)
                    @php
                        $isActive = collect($section['items'])->pluck('key')->contains($cur);
                    @endphp
                    <li class="nav-item menu-section-header {{ $isActive ? 'section-open' : 'section-closed' }}" data-section="{{ $sectionKey }}">
                        <a class="nav-link" href="javascript:void(0);" onclick="toggleMenuSection(this.parentElement)">
                            <i class="ri-arrow-down-s-line section-arrow"></i>
                            <span>{{ $section['label'] }}</span>
                        </a>
                    </li>
                    @foreach($section['items'] as $item)
                        <li class="nav-item menu-section-item {{ $isActive ? '' : 'd-none' }}" data-parent="{{ $sectionKey }}">
                            <a href="{{ $item['param'] ? route($item['route'], ['page' => $item['param']]) : route($item['route']) }}"
                               class="nav-link {{ $cur === $item['key'] ? 'active' : '' }}"
                               {{ $cur === $item['key'] ? 'id=sidebar-active-item' : '' }}>
                                <i class="{{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
</div>

<script>
function toggleMenuSection(header) {
    var section = header.dataset.section;
    var items = document.querySelectorAll('.menu-section-item[data-parent="' + section + '"]');
    var isOpen = header.classList.contains('section-open');

    if (isOpen) {
        header.classList.remove('section-open');
        header.classList.add('section-closed');
        items.forEach(function(item) { item.classList.add('d-none'); });
    } else {
        header.classList.remove('section-closed');
        header.classList.add('section-open');
        items.forEach(function(item) { item.classList.remove('d-none'); });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var active = document.getElementById('sidebar-active-item');
    if (active) {
        active.scrollIntoView({ block: 'center', behavior: 'instant' });
    }
});
</script>
