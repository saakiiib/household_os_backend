@extends('admin.pages.master')
@section('title', 'Users')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Users</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="usersTable" class="table table-bordered table-striped align-middle" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
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
@endsection

@section('script')
<script>
function toggleStatus(id) {
    $.ajax({
        url: '/admin/users/' + id + '/toggle-status',
        type: 'PATCH',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(res) {
            $('#usersTable').DataTable().ajax.reload(null, false);
        }
    });
}
$(function() {
    $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.users.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name', searchable: false },
            { data: 'email', name: 'email' },
            { data: 'households_count', name: 'households_count', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'date_fmt', name: 'created_at', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' },
        ]
    });
});
</script>
@endsection
