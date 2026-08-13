@extends('admin.pages.adminmaster')
@section('title', 'User Detail')
@section('content')
<div class="page-head">
    <div><h1>{{ $record->name }}</h1><p>User account detail</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'users']) }}">← Back to Users</a></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Account</h3></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                    <tr><td>Name</td><td><b>{{ $record->name }}</b></td></tr>
                    <tr><td>Email</td><td>{{ $record->email }}</td></tr>
                    <tr><td>Admin</td><td><span class="badge {{ $record->is_admin ? 'primary' : 'neutral' }}">{{ $record->is_admin ? 'Yes' : 'No' }}</span></td></tr>
                    <tr><td>Email verified</td><td>{{ $record->email_verified_at ? $record->email_verified_at->format('d M Y') : '—' }}</td></tr>
                    <tr><td>Joined</td><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Households ({{ $record->households->count() }})</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Household</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($record->households as $h)
                    <tr>
                        <td><a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $h->id]) }}">{{ $h->name }}</a></td>
                        <td>{{ ucfirst($h->pivot->role ?? '—') }}</td>
                        <td><span class="badge {{ ($h->pivot->status ?? 'active') === 'active' ? 'success' : 'warning' }}">{{ ucfirst($h->pivot->status ?? 'active') }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="empty">No households</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
