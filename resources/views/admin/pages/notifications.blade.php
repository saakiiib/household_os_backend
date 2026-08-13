@extends('admin.pages.adminmaster')
@section('title', 'Push Notifications')
@section('content')
<div class="page-head">
    <div><h1>Push Notifications</h1><p>System and campaign notifications sent to users.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'notifications']) }}">+ New Notification</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Notifications</h3><span class="badge neutral">{{ $notifications->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Type</th><th>Read</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($notifications as $notification)
                <tr>
                    <td>{{ $notification->title ?? ($notification->data['title'] ?? $notification->type ?? 'Notification') }}</td>
                    <td>{{ $notification->type ?? '—' }}</td>
                    <td><span class="badge {{ $notification->read_at ? 'success' : 'warning' }}">{{ $notification->read_at ? 'Read' : 'Unread' }}</span></td>
                    <td>{{ $notification->created_at->format('d M Y H:i') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'notifications', 'id' => $notification->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="4" class="empty">No notifications yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $notifications->links() }}</div>
</div>
@endsection
