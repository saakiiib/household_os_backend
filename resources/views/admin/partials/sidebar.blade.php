<style>
    /* ═══════════════════════════════════════════════════
       COMPACT RICH GLASS SIDEBAR
       Dense · vibrant · high contrast · eye-catching
       ═══════════════════════════════════════════════════ */

    html[data-layout="vertical"] .app-menu.navbar-menu {
        background: rgba(255, 255, 255, 0.78) !important;
        backdrop-filter: blur(28px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(28px) saturate(160%) !important;
        border-right: 1px solid rgba(0, 0, 0, 0.08) !important;
        box-shadow: 4px 0 24px rgba(15, 23, 42, 0.06) !important;
    }

    html[data-layout="vertical"] .app-menu .navbar-brand-box {
        background: rgba(255, 255, 255, 0.65) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.07) !important;
        padding: 16px 14px !important;
    }

    .navbar-menu .navbar-nav .nav-link::before {
        display: none !important;
    }

    /* Compact section spacing */
    .menu-section-header {
        margin-top: 12px;
        margin-bottom: 2px;
    }
    .menu-section-header:first-of-type {
        margin-top: 8px;
    }

    /* Bold, visible accents */
    .menu-section-header[data-section="overview"]       { --section-accent: #2563EB; --section-soft: rgba(37, 99, 235, 0.14); --section-mid: rgba(37, 99, 235, 0.28); --section-glow: rgba(37, 99, 235, 0.35); }
    .menu-section-header[data-section="people"]         { --section-accent: #059669; --section-soft: rgba(5, 150, 105, 0.14); --section-mid: rgba(5, 150, 105, 0.28); --section-glow: rgba(5, 150, 105, 0.35); }
    .menu-section-header[data-section="operations"]     { --section-accent: #D97706; --section-soft: rgba(217, 119, 6, 0.14); --section-mid: rgba(217, 119, 6, 0.28); --section-glow: rgba(217, 119, 6, 0.35); }
    .menu-section-header[data-section="billing"]        { --section-accent: #7C3AED; --section-soft: rgba(124, 58, 237, 0.14); --section-mid: rgba(124, 58, 237, 0.28); --section-glow: rgba(124, 58, 237, 0.35); }
    .menu-section-header[data-section="support-comms"]  { --section-accent: #0284C7; --section-soft: rgba(2, 132, 199, 0.14); --section-mid: rgba(2, 132, 199, 0.28); --section-glow: rgba(2, 132, 199, 0.35); }
    .menu-section-header[data-section="content"]        { --section-accent: #DB2777; --section-soft: rgba(219, 39, 119, 0.14); --section-mid: rgba(219, 39, 119, 0.28); --section-glow: rgba(219, 39, 119, 0.35); }
    .menu-section-header[data-section="security"]       { --section-accent: #E11D48; --section-soft: rgba(225, 29, 72, 0.14); --section-mid: rgba(225, 29, 72, 0.28); --section-glow: rgba(225, 29, 72, 0.35); }
    .menu-section-header[data-section="insights"]       { --section-accent: #0D9488; --section-soft: rgba(13, 148, 136, 0.14); --section-mid: rgba(13, 148, 136, 0.28); --section-glow: rgba(13, 148, 136, 0.35); }
    .menu-section-header[data-section="platform"]       { --section-accent: #CA8A04; --section-soft: rgba(202, 138, 4, 0.14); --section-mid: rgba(202, 138, 4, 0.28); --section-glow: rgba(202, 138, 4, 0.35); }

    .section-items[data-section-items="overview"]       { --section-accent: #2563EB; --section-soft: rgba(37, 99, 235, 0.14); --section-mid: rgba(37, 99, 235, 0.28); --section-glow: rgba(37, 99, 235, 0.35); }
    .section-items[data-section-items="people"]         { --section-accent: #059669; --section-soft: rgba(5, 150, 105, 0.14); --section-mid: rgba(5, 150, 105, 0.28); --section-glow: rgba(5, 150, 105, 0.35); }
    .section-items[data-section-items="operations"]     { --section-accent: #D97706; --section-soft: rgba(217, 119, 6, 0.14); --section-mid: rgba(217, 119, 6, 0.28); --section-glow: rgba(217, 119, 6, 0.35); }
    .section-items[data-section-items="billing"]        { --section-accent: #7C3AED; --section-soft: rgba(124, 58, 237, 0.14); --section-mid: rgba(124, 58, 237, 0.28); --section-glow: rgba(124, 58, 237, 0.35); }
    .section-items[data-section-items="support-comms"]  { --section-accent: #0284C7; --section-soft: rgba(2, 132, 199, 0.14); --section-mid: rgba(2, 132, 199, 0.28); --section-glow: rgba(2, 132, 199, 0.35); }
    .section-items[data-section-items="content"]        { --section-accent: #DB2777; --section-soft: rgba(219, 39, 119, 0.14); --section-mid: rgba(219, 39, 119, 0.28); --section-glow: rgba(219, 39, 119, 0.35); }
    .section-items[data-section-items="security"]       { --section-accent: #E11D48; --section-soft: rgba(225, 29, 72, 0.14); --section-mid: rgba(225, 29, 72, 0.28); --section-glow: rgba(225, 29, 72, 0.35); }
    .section-items[data-section-items="insights"]       { --section-accent: #0D9488; --section-soft: rgba(13, 148, 136, 0.14); --section-mid: rgba(13, 148, 136, 0.28); --section-glow: rgba(13, 148, 136, 0.35); }
    .section-items[data-section-items="platform"]       { --section-accent: #CA8A04; --section-soft: rgba(202, 138, 4, 0.14); --section-mid: rgba(202, 138, 4, 0.28); --section-glow: rgba(202, 138, 4, 0.35); }

    /* Section headers — compact, colored, glass pills */
    .navbar-menu .navbar-nav .menu-section-header .nav-link {
        padding: 10px 12px !important;
        margin: 0 10px !important;
        color: var(--section-accent) !important;
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        border: 1px solid var(--section-mid) !important;
        box-shadow: 0 1px 4px var(--section-glow) !important;
        border-radius: 9px !important;
        font-size: 11px !important;
        font-weight: 750 !important;
        letter-spacing: 1.1px !important;
        text-transform: uppercase;
        transition: all 0.18s ease;
        line-height: 1.4 !important;
    }
    .navbar-menu .navbar-nav .menu-section-header .nav-link:hover {
        background: var(--section-soft) !important;
        box-shadow: 0 2px 10px var(--section-glow) !important;
    }
    .navbar-menu .navbar-nav .menu-section-header .section-arrow {
        color: var(--section-accent) !important;
    }
    .menu-section-header .section-arrow {
        font-size: 14px;
        width: 18px;
        text-align: center;
        transition: transform 0.22s ease;
        flex-shrink: 0;
    }
    .menu-section-header.section-closed .section-arrow { transform: rotate(-90deg); }
    .menu-section-header.section-open .section-arrow { transform: rotate(0deg); }

    /* Menu items — compact, high contrast */
    .navbar-menu .navbar-nav .section-items .nav-link {
        padding: 8px 12px !important;
        margin: 1px 10px !important;
        color: #1E293B !important;
        background: transparent !important;
        border-radius: 9px !important;
        font-size: 13.5px !important;
        font-weight: 520 !important;
        letter-spacing: -0.01em;
        border: 1px solid transparent !important;
        transition: all 0.15s ease;
        position: relative;
    }
    .navbar-menu .navbar-nav .section-items .nav-link i {
        color: #64748B !important;
        font-size: 16px !important;
        margin-right: 10px !important;
        width: 18px;
        text-align: center;
        transition: color 0.15s ease;
    }
    .navbar-menu .navbar-nav .section-items .nav-link:hover {
        color: #0F172A !important;
        background: rgba(255, 255, 255, 0.85) !important;
        border-color: rgba(0, 0, 0, 0.06) !important;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06) !important;
    }
    .navbar-menu .navbar-nav .section-items .nav-link:hover i {
        color: var(--section-accent) !important;
    }

    /* Active — strong, visible, rich */
    .navbar-menu .navbar-nav .section-items .nav-link.active {
        color: var(--section-accent) !important;
        background: var(--section-soft) !important;
        border-color: var(--section-mid) !important;
        font-weight: 650 !important;
        box-shadow: 0 2px 12px var(--section-glow) !important;
    }
    .navbar-menu .navbar-nav .section-items .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 3px;
        border-radius: 0 4px 4px 0;
        background: var(--section-accent);
        box-shadow: 0 0 8px var(--section-glow);
    }
    .navbar-menu .navbar-nav .section-items .nav-link.active i {
        color: var(--section-accent) !important;
    }

    /* Scrollbar */
    #scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(100, 116, 139, 0.35) transparent;
    }
    #scrollbar::-webkit-scrollbar { width: 5px; }
    #scrollbar::-webkit-scrollbar-track { background: transparent; }
    #scrollbar::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.3);
        border-radius: 10px;
    }
    #scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(100, 116, 139, 0.5);
    }

    html[data-sidebar-size="sm"] .menu-section-header,
    html[data-sidebar-size="sm"] .section-items { display: none !important; }

    /* Bottom scroll space in sidebar */
    .navbar-menu #scrollbar .navbar-nav {
        padding-bottom: 120px !important;
    }
</style>

@php
    $settingLogo = \App\Models\Setting::get('logo');
    $companyName = \App\Models\Setting::get('company_name', 'Household OS');
@endphp

<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
            @if($settingLogo)
                <span class="logo-lg"><img src="{{ asset($settingLogo) }}" alt="{{ $companyName }}" height="32"></span>
            @else
                <span class="logo-lg"><b>{{ $companyName }}</b></span>
            @endif
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
            @if($settingLogo)
                <span class="logo-lg"><img src="{{ asset($settingLogo) }}" alt="{{ $companyName }}" height="32"></span>
            @else
                <span class="logo-lg"><b>{{ $companyName }}</b></span>
            @endif
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
                        str_starts_with($routeName, 'admin.support') => 'support-communications',
                        default => ($active ?? request()->route('page') ?? ''),
                    };

                    $sections = [
                        'overview' => ['dashboard', 'ai-insights', 'system-status'],
                        'people' => ['users', 'households', 'invitations', 'admins'],
                        'operations' => ['tasks', 'renewals', 'documents', 'ocr-queue', 'storage', 'automations'],
                        'billing' => ['subscriptions', 'payments', 'revenue'],
                        'support-comms' => ['support-communications', 'escalations', 'communications', 'notifications', 'templates'],
                        'content' => ['website-cms', 'app-cms', 'blog', 'media'],
                        'security' => ['audit-logs', 'devices', 'fraud', 'api-logs', 'recycle-bin'],
                        'insights' => ['analytics', 'reports', 'health-scores', 'activity-map'],
                        'platform' => ['feature-flags', 'backups', 'settings'],
                    ];

                    $activeSection = 'overview';
                    foreach ($sections as $section => $pages) {
                        if (in_array($cur, $pages)) {
                            $activeSection = $section;
                            break;
                        }
                    }
                @endphp

                {{-- OVERVIEW --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'overview' ? 'section-open' : 'section-closed' }}" data-section="overview">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Overview</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'overview' ? 'd-none' : '' }}" data-section-items="overview"><a href="{{ route('admin.dashboard') }}" class="nav-link {{ $cur === 'dashboard' ? 'active' : '' }}"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'overview' ? 'd-none' : '' }}" data-section-items="overview"><a href="{{ route('admin.page', ['page' => 'ai-insights']) }}" class="nav-link {{ $cur === 'ai-insights' ? 'active' : '' }}"><i class="ri-robot-line"></i><span>AI Insights</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'overview' ? 'd-none' : '' }}" data-section-items="overview"><a href="{{ route('admin.page', ['page' => 'system-status']) }}" class="nav-link {{ $cur === 'system-status' ? 'active' : '' }}"><i class="ri-server-line"></i><span>System Status</span></a></li>

                {{-- PEOPLE --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'people' ? 'section-open' : 'section-closed' }}" data-section="people">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>People</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'people' ? 'd-none' : '' }}" data-section-items="people"><a href="{{ route('admin.users.index') }}" class="nav-link {{ $cur === 'users' ? 'active' : '' }}"><i class="ri-user-line"></i><span>Users</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'people' ? 'd-none' : '' }}" data-section-items="people"><a href="{{ route('admin.households.index') }}" class="nav-link {{ $cur === 'households' ? 'active' : '' }}"><i class="ri-home-line"></i><span>Households</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'people' ? 'd-none' : '' }}" data-section-items="people"><a href="{{ route('admin.page', ['page' => 'invitations']) }}" class="nav-link {{ $cur === 'invitations' ? 'active' : '' }}"><i class="ri-mail-send-line"></i><span>Invitations</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'people' ? 'd-none' : '' }}" data-section-items="people"><a href="{{ route('admin.admins.index') }}" class="nav-link {{ $cur === 'admins' ? 'active' : '' }}"><i class="ri-shield-user-line"></i><span>Admins</span></a></li>

                {{-- OPERATIONS --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'operations' ? 'section-open' : 'section-closed' }}" data-section="operations">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Operations</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'operations' ? 'd-none' : '' }}" data-section-items="operations"><a href="{{ route('admin.tasks.index') }}" class="nav-link {{ $cur === 'tasks' ? 'active' : '' }}"><i class="ri-task-line"></i><span>Tasks</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'operations' ? 'd-none' : '' }}" data-section-items="operations"><a href="{{ route('admin.renewals.index') }}" class="nav-link {{ $cur === 'renewals' ? 'active' : '' }}"><i class="ri-refresh-line"></i><span>Renewals</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'operations' ? 'd-none' : '' }}" data-section-items="operations"><a href="{{ route('admin.documents.index') }}" class="nav-link {{ $cur === 'documents' ? 'active' : '' }}"><i class="ri-file-text-line"></i><span>Documents</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'operations' ? 'd-none' : '' }}" data-section-items="operations"><a href="{{ route('admin.page', ['page' => 'ocr-queue']) }}" class="nav-link {{ $cur === 'ocr-queue' ? 'active' : '' }}"><i class="ri-scan-line"></i><span>OCR Queue</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'operations' ? 'd-none' : '' }}" data-section-items="operations"><a href="{{ route('admin.page', ['page' => 'storage']) }}" class="nav-link {{ $cur === 'storage' ? 'active' : '' }}"><i class="ri-hard-drive-2-line"></i><span>Storage Explorer</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'operations' ? 'd-none' : '' }}" data-section-items="operations"><a href="{{ route('admin.page', ['page' => 'automations']) }}" class="nav-link {{ $cur === 'automations' ? 'active' : '' }}"><i class="ri-flashlight-line"></i><span>Automations</span></a></li>

                {{-- BILLING --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'billing' ? 'section-open' : 'section-closed' }}" data-section="billing">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Billing</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'billing' ? 'd-none' : '' }}" data-section-items="billing"><a href="{{ route('admin.subscriptions.index') }}" class="nav-link {{ $cur === 'subscriptions' ? 'active' : '' }}"><i class="ri-star-line"></i><span>Subscriptions</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'billing' ? 'd-none' : '' }}" data-section-items="billing"><a href="{{ route('admin.payments.index') }}" class="nav-link {{ $cur === 'payments' ? 'active' : '' }}"><i class="ri-money-pound-circle-line"></i><span>Payments</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'billing' ? 'd-none' : '' }}" data-section-items="billing"><a href="{{ route('admin.page', ['page' => 'revenue']) }}" class="nav-link {{ $cur === 'revenue' ? 'active' : '' }}"><i class="ri-line-chart-line"></i><span>Revenue Analytics</span></a></li>

                {{-- SUPPORT & COMMS --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'support-comms' ? 'section-open' : 'section-closed' }}" data-section="support-comms">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Support &amp; Comms</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'support-comms' ? 'd-none' : '' }}" data-section-items="support-comms"><a href="{{ route('admin.support.index') }}" class="nav-link {{ $cur === 'support-communications' ? 'active' : '' }}"><i class="ri-customer-service-2-line"></i><span>Support Communications</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'support-comms' ? 'd-none' : '' }}" data-section-items="support-comms"><a href="{{ route('admin.page', ['page' => 'escalations']) }}" class="nav-link {{ $cur === 'escalations' ? 'active' : '' }}"><i class="ri-alarm-warning-line"></i><span>Escalations</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'support-comms' ? 'd-none' : '' }}" data-section-items="support-comms"><a href="{{ route('admin.page', ['page' => 'communications']) }}" class="nav-link {{ $cur === 'communications' ? 'active' : '' }}"><i class="ri-chat-3-line"></i><span>Communication Centre</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'support-comms' ? 'd-none' : '' }}" data-section-items="support-comms"><a href="{{ route('admin.page', ['page' => 'notifications']) }}" class="nav-link {{ $cur === 'notifications' ? 'active' : '' }}"><i class="ri-notification-3-line"></i><span>Push Notifications</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'support-comms' ? 'd-none' : '' }}" data-section-items="support-comms"><a href="{{ route('admin.page', ['page' => 'templates']) }}" class="nav-link {{ $cur === 'templates' ? 'active' : '' }}"><i class="ri-file-text-line"></i><span>Message Templates</span></a></li>

                {{-- CONTENT --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'content' ? 'section-open' : 'section-closed' }}" data-section="content">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Content</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'content' ? 'd-none' : '' }}" data-section-items="content"><a href="{{ route('admin.page', ['page' => 'website-cms']) }}" class="nav-link {{ $cur === 'website-cms' ? 'active' : '' }}"><i class="ri-global-line"></i><span>Website CMS</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'content' ? 'd-none' : '' }}" data-section-items="content"><a href="{{ route('admin.page', ['page' => 'app-cms']) }}" class="nav-link {{ $cur === 'app-cms' ? 'active' : '' }}"><i class="ri-smartphone-line"></i><span>Mobile App CMS</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'content' ? 'd-none' : '' }}" data-section-items="content"><a href="{{ route('admin.page', ['page' => 'blog']) }}" class="nav-link {{ $cur === 'blog' ? 'active' : '' }}"><i class="ri-article-line"></i><span>Blog</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'content' ? 'd-none' : '' }}" data-section-items="content"><a href="{{ route('admin.page', ['page' => 'media']) }}" class="nav-link {{ $cur === 'media' ? 'active' : '' }}"><i class="ri-image-line"></i><span>Media Library</span></a></li>

                {{-- SECURITY --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'security' ? 'section-open' : 'section-closed' }}" data-section="security">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Security</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'security' ? 'd-none' : '' }}" data-section-items="security"><a href="{{ route('admin.page', ['page' => 'audit-logs']) }}" class="nav-link {{ $cur === 'audit-logs' ? 'active' : '' }}"><i class="ri-list-check-2"></i><span>Audit Logs</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'security' ? 'd-none' : '' }}" data-section-items="security"><a href="{{ route('admin.page', ['page' => 'devices']) }}" class="nav-link {{ $cur === 'devices' ? 'active' : '' }}"><i class="ri-device-line"></i><span>Device Manager</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'security' ? 'd-none' : '' }}" data-section-items="security"><a href="{{ route('admin.page', ['page' => 'fraud']) }}" class="nav-link {{ $cur === 'fraud' ? 'active' : '' }}"><i class="ri-spy-line"></i><span>Fraud Detection</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'security' ? 'd-none' : '' }}" data-section-items="security"><a href="{{ route('admin.page', ['page' => 'api-logs']) }}" class="nav-link {{ $cur === 'api-logs' ? 'active' : '' }}"><i class="ri-code-line"></i><span>API Access</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'security' ? 'd-none' : '' }}" data-section-items="security"><a href="{{ route('admin.page', ['page' => 'recycle-bin']) }}" class="nav-link {{ $cur === 'recycle-bin' ? 'active' : '' }}"><i class="ri-delete-bin-line"></i><span>Recycle Bin</span></a></li>

                {{-- INSIGHTS --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'insights' ? 'section-open' : 'section-closed' }}" data-section="insights">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Insights</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'insights' ? 'd-none' : '' }}" data-section-items="insights"><a href="{{ route('admin.page', ['page' => 'analytics']) }}" class="nav-link {{ $cur === 'analytics' ? 'active' : '' }}"><i class="ri-pie-chart-2-line"></i><span>Platform Analytics</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'insights' ? 'd-none' : '' }}" data-section-items="insights"><a href="{{ route('admin.page', ['page' => 'reports']) }}" class="nav-link {{ $cur === 'reports' ? 'active' : '' }}"><i class="ri-file-chart-line"></i><span>Reports</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'insights' ? 'd-none' : '' }}" data-section-items="insights"><a href="{{ route('admin.page', ['page' => 'health-scores']) }}" class="nav-link {{ $cur === 'health-scores' ? 'active' : '' }}"><i class="ri-heart-pulse-line"></i><span>Health Scores</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'insights' ? 'd-none' : '' }}" data-section-items="insights"><a href="{{ route('admin.page', ['page' => 'activity-map']) }}" class="nav-link {{ $cur === 'activity-map' ? 'active' : '' }}"><i class="ri-map-pin-line"></i><span>Live Activity</span></a></li>

                {{-- PLATFORM --}}
                <li class="nav-item menu-section-header {{ $activeSection === 'platform' ? 'section-open' : 'section-closed' }}" data-section="platform">
                    <a href="javascript:void(0);" class="nav-link">
                        <i class="ri-arrow-down-s-line section-arrow"></i>
                        <span>Platform</span>
                    </a>
                </li>
                <li class="nav-item section-items {{ $activeSection !== 'platform' ? 'd-none' : '' }}" data-section-items="platform"><a href="{{ route('admin.page', ['page' => 'feature-flags']) }}" class="nav-link {{ $cur === 'feature-flags' ? 'active' : '' }}"><i class="ri-flag-line"></i><span>Feature Flags</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'platform' ? 'd-none' : '' }}" data-section-items="platform"><a href="{{ route('admin.page', ['page' => 'backups']) }}" class="nav-link {{ $cur === 'backups' ? 'active' : '' }}"><i class="ri-archive-line"></i><span>Backup Manager</span></a></li>
                <li class="nav-item section-items {{ $activeSection !== 'platform' ? 'd-none' : '' }}" data-section-items="platform"><a href="{{ route('admin.settings.index') }}" class="nav-link {{ $cur === 'settings' ? 'active' : '' }}"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function(e) {
    var header = e.target.closest('.menu-section-header');
    if (!header) return;
    e.preventDefault();
    e.stopPropagation();
    var section = header.getAttribute('data-section');
    var items = document.querySelectorAll('[data-section-items="' + section + '"]');
    if (header.classList.contains('section-open')) {
        header.classList.remove('section-open');
        header.classList.add('section-closed');
        items.forEach(function(item) { item.classList.add('d-none'); });
    } else {
        header.classList.remove('section-closed');
        header.classList.add('section-open');
        items.forEach(function(item) { item.classList.remove('d-none'); });
    }
}, true);
</script>
