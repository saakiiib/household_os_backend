@extends('admin.pages.master')
@section('title', 'Households')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Households</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="householdsTable" class="table table-bordered table-striped align-middle" width="100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Household</th>
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
            { data: 'name_link', name: 'name', orderable: true, searchable: true },
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
