@extends('admin.pages.master')
@section('title', 'Subscriptions')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Subscriptions</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="subscriptionsTable" class="table table-bordered table-striped align-middle" width="100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Household</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Period End</th>
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
$(function() {
    $('#subscriptionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.subscriptions.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user_link', name: 'user_name', orderable: false, searchable: false },
            { data: 'household_link', name: 'household_name', orderable: false, searchable: false },
            { data: 'plan_name', name: 'plan_name', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: true },
            { data: 'period_end_fmt', name: 'current_period_end', orderable: false, searchable: false },
        ]
    });
});
</script>
@endsection
