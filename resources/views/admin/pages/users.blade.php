@extends('admin.pages.master')
@section('title', 'Users')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Users</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-bordered table-striped align-middle" width="100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Households</th>
                                    <th>Status</th>
                                    <th>Joined</th>
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
    $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.users.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name_link', name: 'first_name', orderable: true, searchable: true },
            { data: 'email_link', name: 'email', orderable: true, searchable: true },
            { data: 'households_count', name: 'households_count', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: true },
            { data: 'date_fmt', name: 'created_at', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' },
        ]
    });
});

function toggleStatus(userId) {
    if (!confirm('Toggle user status?')) return;
    $.ajax({
        url: '/admin/users/' + userId + '/toggle-status',
        type: 'PATCH',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(res) {
            if (res.success) location.reload();
        }
    });
}
</script>
@endsection
