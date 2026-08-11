@extends('admin.pages.master')
@section('title', 'Tasks')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">All Tasks</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tasksTable" class="table table-bordered table-striped align-middle" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Household</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Due Date</th>
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
    $('#tasksTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.tasks.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'title', name: 'title' },
            { data: 'household_name', name: 'household_name', orderable: false, searchable: false },
            { data: 'assigned_name', name: 'assigned_name', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'due_date_fmt', name: 'due_date', orderable: false, searchable: false },
        ]
    });
});
</script>
@endsection
