@extends('admin.pages.adminmaster')
@section('title', 'User Management')
@section('content')
<div class="page-head">
    <div><h1>Users</h1><p>Manage every account across the platform.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'users']) }}">+ New User</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Users</h3><span class="badge neutral">{{ $users->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Households</th><th>Admin</th><th>Joined</th><th></th></tr></thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td><div class="avatar" style="display:inline-grid;width:30px;height:30px;border-radius:8px;place-items:center;background:#eef2ff;color:#4338ca;font-size:12px">{{ strtoupper(substr($user->name,0,2)) }}</div> {{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->households_count ?? $user->households->count() }}</td>
                    <td><span class="badge {{ $user->is_admin ? 'primary' : 'neutral' }}">{{ $user->is_admin ? 'Yes' : 'No' }}</span></td>
                    <td>{{ $user->created_at->format('d M Y') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'users', 'id' => $user->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty">No users yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $users->links() }}</div>
</div>
@endsection
