@extends('admin.pages.master')
@section('title', 'Push Notification Management')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Push Notification Management</h4>
                    <p class="text-muted mb-0">Create, target, schedule and monitor push notifications.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-upload-2-line"></i> Import</button>
                    <button class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Create</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Notifications</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $notifications->total() ?? $notifications->count() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Notification</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($notifications as $notification)
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $notification->title ?? '—' }}</div>
                                            <div class="text-muted fs-13">{{ Str::limit($notification->body ?? '', 60) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info">{{ ucfirst($notification->type ?? 'general') }}</span>
                                        </td>
                                        <td>
                                            @if (!empty($notification->read_at))
                                                <span class="badge bg-soft-secondary text-secondary">Read</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning">Unread</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $notification->created_at ? $notification->created_at->format('d M Y H:i') : '—' }}</td>
                                        <td>
                                            <a href="{{ route('admin.page.detail', ['page' => 'notifications', 'id' => $notification->id]) }}" class="btn btn-sm btn-soft-primary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No notifications yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if (method_exists($notifications, 'links'))
                        <div class="mt-3">{{ $notifications->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
