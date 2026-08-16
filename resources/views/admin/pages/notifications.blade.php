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
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total</p>
                            <h4 class="mb-0">{{ number_format($totalNotifications) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-notification-3-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Unread</p>
                            <h4 class="mb-0">{{ number_format($unreadNotifications) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-mail-open-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Read</p>
                            <h4 class="mb-0">{{ number_format($readNotifications) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-mail-check-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Notifications</h4>
                    <span class="badge bg-soft-primary fs-12">{{ number_format($totalNotifications) }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="notifications-table" class="table table-sm table-nowrap align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Notification</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('script')
<script>
$(function () {
    $('#notifications-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.page', ['page' => 'notifications']) }}",
        columns: [
            { data: 'notification', name: 'title', orderable: true, searchable: true },
            { data: 'type', name: 'type', orderable: false, searchable: false },
            { data: 'status', name: 'read_at', orderable: false, searchable: false },
            { data: 'created', name: 'created_at', orderable: true, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        language: { emptyTable: 'No notifications yet', zeroRecords: 'No matching notifications' }
    });
});
</script>
@endsection
