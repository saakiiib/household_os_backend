@extends('admin.pages.master')
@section('title', 'Subscriptions')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Subscriptions</h4>
        </div>
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
            { data: 'user_name', name: 'user_name', orderable: false, searchable: false },
            { data: 'household_name', name: 'household_name', orderable: false, searchable: false },
            { data: 'plan_name', name: 'plan_name', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'period_end_fmt', name: 'current_period_end', orderable: false, searchable: false },
        ]
    });
});
</script>
@endsection
