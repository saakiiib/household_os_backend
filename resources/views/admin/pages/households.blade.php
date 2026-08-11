@extends('admin.pages.master')
@section('title', 'Households')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Households</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="householdsTable" class="table table-bordered table-striped align-middle" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Created By</th>
                            <th>Members</th>
                            <th>Subscription</th>
                            <th>Created</th>
                            <th class="text-center" style="width:60px">Action</th>
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
    $('#householdsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.households.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'creator_name', name: 'creator_name', orderable: false, searchable: false },
            { data: 'members_count', name: 'members_count', orderable: false, searchable: false },
            { data: 'subscription_status', name: 'subscription_status', orderable: false, searchable: false },
            { data: 'date_fmt', name: 'created_at', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' },
        ]
    });
});
</script>
@endsection
