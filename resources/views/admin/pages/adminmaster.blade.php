<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · Household OS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('backend-pages/assets/css/admin.css') }}">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">HO</div>
            <div><b>Household OS</b><small>Enterprise Admin</small></div>
        </div>

        <div class="nav-section">Overview</div>
        <a class="nav-link {{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'dashboard']) }}"><span class="ico">◫</span>Dashboard</a>
        <a class="nav-link {{ $active === 'ai-insights' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'ai-insights']) }}"><span class="ico">✦</span>AI Insights</a>
        <a class="nav-link {{ $active === 'system-status' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'system-status']) }}"><span class="ico">◉</span>System Status</a>

        <div class="nav-section">People</div>
        <a class="nav-link {{ $active === 'users' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'users']) }}"><span class="ico">♙</span>Users</a>
        <a class="nav-link {{ $active === 'households' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'households']) }}"><span class="ico">⌂</span>Households</a>
        <a class="nav-link {{ $active === 'invitations' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'invitations']) }}"><span class="ico">✉</span>Invitations</a>
        <a class="nav-link {{ $active === 'admin-roles' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'admin-roles']) }}"><span class="ico">⚿</span>Admin Roles</a>

        <div class="nav-section">Operations</div>
        <a class="nav-link {{ $active === 'tasks' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'tasks']) }}"><span class="ico">✓</span>Tasks</a>
        <a class="nav-link {{ $active === 'renewals' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'renewals']) }}"><span class="ico">↻</span>Renewals</a>
        <a class="nav-link {{ $active === 'documents' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'documents']) }}"><span class="ico">▤</span>Documents</a>
        <a class="nav-link {{ $active === 'ocr-queue' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'ocr-queue']) }}"><span class="ico">⌁</span>OCR Queue<span class="count">9</span></a>
        <a class="nav-link {{ $active === 'storage' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'storage']) }}"><span class="ico">▣</span>Storage Explorer</a>
        <a class="nav-link {{ $active === 'automations' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'automations']) }}"><span class="ico">⚡</span>Automations</a>

        <div class="nav-section">Billing</div>
        <a class="nav-link {{ $active === 'subscriptions' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'subscriptions']) }}"><span class="ico">★</span>Subscriptions</a>
        <a class="nav-link {{ $active === 'payments' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'payments']) }}"><span class="ico">£</span>Payments</a>
        <a class="nav-link {{ $active === 'refunds' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'refunds']) }}"><span class="ico">↩</span>Refunds</a>
        <a class="nav-link {{ $active === 'coupons' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'coupons']) }}"><span class="ico">%</span>Coupons</a>
        <a class="nav-link {{ $active === 'revenue' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'revenue']) }}"><span class="ico">↗</span>Revenue Analytics</a>

        <div class="nav-section">Support &amp; Comms</div>
        <a class="nav-link {{ $active === 'tickets' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'tickets']) }}"><span class="ico">?</span>Support Tickets<span class="count">9</span></a>
        <a class="nav-link {{ $active === 'escalations' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'escalations']) }}"><span class="ico">!</span>Escalations</a>
        <a class="nav-link {{ $active === 'communications' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'communications']) }}"><span class="ico">☏</span>Communication Centre</a>
        <a class="nav-link {{ $active === 'notifications' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'notifications']) }}"><span class="ico">●</span>Push Notifications</a>
        <a class="nav-link {{ $active === 'templates' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'templates']) }}"><span class="ico">▧</span>Message Templates</a>

        <div class="nav-section">Content</div>
        <a class="nav-link {{ $active === 'website-cms' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'website-cms']) }}"><span class="ico">◧</span>Website CMS</a>
        <a class="nav-link {{ $active === 'app-cms' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'app-cms']) }}"><span class="ico">▱</span>Mobile App CMS</a>
        <a class="nav-link {{ $active === 'blog' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'blog']) }}"><span class="ico">✎</span>Blog</a>
        <a class="nav-link {{ $active === 'media' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'media']) }}"><span class="ico">▦</span>Media Library</a>

        <div class="nav-section">Security</div>
        <a class="nav-link {{ $active === 'audit-logs' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'audit-logs']) }}"><span class="ico">≣</span>Audit Logs</a>
        <a class="nav-link {{ $active === 'devices' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'devices']) }}"><span class="ico">▯</span>Device Manager</a>
        <a class="nav-link {{ $active === 'fraud' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'fraud']) }}"><span class="ico">⚠</span>Fraud Detection</a>
        <a class="nav-link {{ $active === 'api-logs' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'api-logs']) }}"><span class="ico">⌘</span>API Access</a>
        <a class="nav-link {{ $active === 'recycle-bin' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'recycle-bin']) }}"><span class="ico">♲</span>Recycle Bin</a>

        <div class="nav-section">Insights</div>
        <a class="nav-link {{ $active === 'analytics' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'analytics']) }}"><span class="ico">◩</span>Platform Analytics</a>
        <a class="nav-link {{ $active === 'reports' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'reports']) }}"><span class="ico">▥</span>Reports</a>
        <a class="nav-link {{ $active === 'health-scores' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'health-scores']) }}"><span class="ico">♥</span>Health Scores</a>
        <a class="nav-link {{ $active === 'activity-map' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'activity-map']) }}"><span class="ico">◎</span>Live Activity</a>

        <div class="nav-section">Platform</div>
        <a class="nav-link {{ $active === 'feature-flags' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'feature-flags']) }}"><span class="ico">⚑</span>Feature Flags</a>
        <a class="nav-link {{ $active === 'backups' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'backups']) }}"><span class="ico">⛁</span>Backup Manager</a>
        <a class="nav-link {{ $active === 'settings' ? 'active' : '' }}" href="{{ route('admin.page', ['page' => 'settings']) }}"><span class="ico">⚙</span>Settings</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <button class="icon-btn mobile-toggle">☰</button>
            <div class="search"><input placeholder="Search users, households, documents, tickets..."></div>
            <div class="top-actions">
                <a class="icon-btn" href="{{ route('admin.page', ['page' => 'system-status']) }}" title="Incidents">◉</a>
                <button class="icon-btn" data-toast="Notifications opened" title="Notifications">●</button>
                <div class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}</div>
            </div>
        </header>

        <section class="content">
            @yield('content')
        </section>
    </main>
</div>

<script src="{{ asset('backend-pages/assets/js/admin.js') }}"></script>
@yield('script')
</body>
</html>
