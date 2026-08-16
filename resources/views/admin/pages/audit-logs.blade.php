@extends('admin.pages.master')
@section('title', 'Audit Logs')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Audit Logs</h4>
                    <p class="text-muted mb-0">Immutable history of sensitive admin, user and system actions.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Export</button>
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
                            <p class="text-muted mb-2 text-truncate">Total Entries</p>
                            <h4 class="mb-0">{{ number_format($totalLogs) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-list-check-2"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Today</p>
                            <h4 class="mb-0">{{ number_format($todayLogs) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-calendar-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">Activity</h4>
                    <span class="badge bg-soft-primary fs-12">{{ number_format($totalLogs) }} entries</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="audit-logs-table" class="table table-sm table-nowrap align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>Details</th>
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
    $('#audit-logs-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.page', ['page' => 'audit-logs']) }}",
        columns: [
            { data: 'time', name: 'created_at', orderable: true, searchable: false },
            { data: 'actor', name: 'causer_id', orderable: false, searchable: false },
            { data: 'action_name', name: 'description', orderable: false, searchable: true },
            { data: 'target', name: 'subject_type', orderable: false, searchable: false },
            { data: 'details', name: 'details', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: { emptyTable: 'No audit events recorded yet', zeroRecords: 'No matching events' }
    });
});
</script>
@endsection
