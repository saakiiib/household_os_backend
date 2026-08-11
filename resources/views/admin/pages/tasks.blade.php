@extends('admin.pages.master')
@section('title', 'Tasks')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Tasks</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tasksTable" class="table table-bordered table-striped align-middle" width="100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Task</th>
                                    <th>Household</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
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
    $('#tasksTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.tasks.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'title_link', name: 'title', orderable: true, searchable: true },
            { data: 'household_link', name: 'household_name', orderable: false, searchable: false },
            { data: 'assigned_name', name: 'assigned_name', orderable: false, searchable: false },
            { data: 'priority_badge', name: 'priority', orderable: true },
            { data: 'status_badge', name: 'status', orderable: true },
            { data: 'due_date_fmt', name: 'due_date', orderable: true },
        ]
    });
});
</script>
@endsection
