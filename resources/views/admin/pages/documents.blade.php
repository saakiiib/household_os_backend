@extends('admin.pages.master')
@section('title', 'Documents')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">All Documents</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="documentsTable" class="table table-bordered table-striped align-middle" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Household</th>
                            <th>Files</th>
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
    $('#documentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.documents.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'title', name: 'title' },
            { data: 'category_fmt', name: 'category', orderable: false, searchable: false },
            { data: 'household_name', name: 'household_name', orderable: false, searchable: false },
            { data: 'files_count', name: 'files_count', orderable: false, searchable: false },
            { data: 'due_date_fmt', name: 'due_date', orderable: false, searchable: false },
        ]
    });
});
</script>
@endsection
