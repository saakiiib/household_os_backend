@extends('admin.pages.adminmaster')
@section('title', 'Executive Dashboard')
@section('content')
<div class="page-head">
    <div>
        <h1>Executive Dashboard</h1>
        <p>A real-time view of platform growth, revenue, engagement, operations and risk.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('admin.page', ['page' => 'reports']) }}">Download report</a>
        <a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'communications']) }}">Create announcement</a>
    </div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-top"><div class="kpi-icon">⌂</div><span class="badge neutral">Live</span></div><div class="value">{{ number_format($totalHouseholds) }}</div><div class="label">Total Households</div><div class="trend">vs last period</div></div>
    <div class="card kpi"><div class="kpi-top"><div class="kpi-icon">♙</div><span class="badge neutral">Live</span></div><div class="value">{{ number_format($totalUsers) }}</div><div class="label">Total Users</div><div class="trend">vs last period</div></div>
    <div class="card kpi"><div class="kpi-top"><div class="kpi-icon">★</div><span class="badge neutral">Live</span></div><div class="value">{{ number_format($activeSubscriptions) }}</div><div class="label">Active Subscriptions</div><div class="trend">vs last period</div></div>
    <div class="card kpi"><div class="kpi-top"><div class="kpi-icon">£</div><span class="badge neutral">Live</span></div><div class="value">${{ number_format($totalRevenue, 2) }}</div><div class="label">Total Revenue</div><div class="trend">vs last period</div></div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-icon">✓</div><div class="value">{{ number_format($totalTasks) }}</div><div class="label">Total Tasks</div><div class="trend danger">{{ $pendingTasks }} pending</div></div>
    <div class="card kpi"><div class="kpi-icon">↻</div><div class="value">{{ number_format($totalRenewals) }}</div><div class="label">Total Renewals</div><div class="trend danger">{{ $overdueRenewals }} overdue</div></div>
    <div class="card kpi"><div class="kpi-icon">▤</div><div class="value">{{ number_format($totalDocuments) }}</div><div class="label">Total Documents</div><div class="trend">stored</div></div>
    <div class="card kpi"><div class="kpi-icon">✉</div><div class="value">{{ number_format($openInvitations) }}</div><div class="label">Open Invitations</div><div class="trend">pending accept</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Recent Users</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                    @forelse($recentUsers as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="badge {{ $user->is_admin ? 'primary' : 'neutral' }}">{{ $user->is_admin ? 'Admin' : 'User' }}</span></td>
                        <td>{{ $user->created_at->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="empty">No users yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Recent Payments</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>User</th><th>Amount</th><th>Gateway</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($recentPayments as $payment)
                    <tr>
                        <td>{{ $payment->user->name ?? 'N/A' }}</td>
                        <td>${{ number_format($payment->amount, 2) }}</td>
                        <td>{{ ucfirst($payment->gateway ?? '—') }}</td>
                        <td><span class="badge {{ $payment->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($payment->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="empty">No payments yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
