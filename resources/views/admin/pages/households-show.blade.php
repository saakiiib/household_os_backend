@extends('admin.pages.adminmaster')
@section('title', 'Household Detail')
@section('content')
<div class="page-head">
    <div><h1>{{ $record->name }}</h1><p>Invite code <code>{{ $record->invite_code }}</code></p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'households']) }}">← Back to Households</a></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-head"><h3>Overview</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Name</td><td><b>{{ $record->name }}</b></td></tr>
                <tr><td>Description</td><td>{{ $record->description ?? '—' }}</td></tr>
                <tr><td>Invite code</td><td><code>{{ $record->invite_code }}</code></td></tr>
                <tr><td>Created</td><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Members ({{ $record->members->count() }})</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>User</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($record->members as $m)
                    <tr><td><a href="{{ route('admin.page.detail', ['page' => 'users', 'id' => $m->id]) }}">{{ $m->name }}</a></td><td>{{ ucfirst($m->pivot->role ?? '—') }}</td><td><span class="badge {{ ($m->pivot->status ?? 'active') === 'active' ? 'success' : 'warning' }}">{{ ucfirst($m->pivot->status ?? 'active') }}</span></td></tr>
                    @empty
                    <tr><td colspan="3" class="empty">No members</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Subscription</h3></div>
        <div class="table-wrap">
            @if($record->subscription)
                <table class="table">
                    <tbody>
                        <tr><td>Plan</td><td>{{ $record->subscription->plan->name ?? '—' }}</td></tr>
                        <tr><td>Status</td><td><span class="badge {{ $record->subscription->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($record->subscription->status) }}</span></td></tr>
                        <tr><td>Period end</td><td>{{ $record->subscription->current_period_end ? $record->subscription->current_period_end->format('d M Y') : '—' }}</td></tr>
                    </tbody>
                </table>
            @else
                <p class="empty">No active subscription</p>
            @endif
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-head"><h3>Related Records</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Tasks</td><td>{{ $record->tasks->count() }}</td></tr>
                <tr><td>Documents</td><td>{{ $record->documents->count() }}</td></tr>
                <tr><td>Renewals</td><td>{{ $record->renewals->count() }}</td></tr>
                <tr><td>Payments</td><td>{{ $record->payments->count() }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
