@extends('admin.pages.master')
@section('title', 'Admins')

@section('content')

    <div class="container-fluid mb-3">
        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" id="newBtn">
                <i class="ri-add-line me-1"></i> Add New Admin
            </button>
        </div>
    </div>

    <div class="container-fluid" id="addThisFormContainer" style="display:none;">
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1" id="cardTitle">Add New Admin</h4>
                    </div>
                    <div class="card-body">
                        <input type="hidden" id="adminId">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" placeholder="First name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" placeholder="Last name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" placeholder="Email address" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger" id="passwordRequired">*</span></label>
                                <input type="password" class="form-control" id="password" placeholder="Min 6 characters" minlength="6">
                                <small class="text-muted" id="passwordHint">Minimum 6 characters.</small>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-primary" id="saveBtn">
                            <i class="ri-save-line me-1"></i> Save
                        </button>
                        <button class="btn btn-light ms-1" id="cancelBtn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid" id="contentContainer">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">All Admins</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="admins-table" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Added</th>
                                        <th>Status</th>
                                        <th>Action</th>
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
$(document).ready(function () {

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    var table = $('#admins-table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        ajax: "{{ route('admin.admins.index') }}",
        columns: [
            { data: 'name', name: 'first_name', orderable: true, searchable: true },
            { data: 'email', name: 'email', orderable: true, searchable: true },
            { data: 'added', name: 'created_at', orderable: true, searchable: false },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[2, 'desc']],
        language: { emptyTable: 'No admins found', zeroRecords: 'No matching admins' }
    });

    function clearForm() {
        $('#adminId').val('');
        $('#first_name, #last_name, #email, #password').val('');
        $('#passwordRequired').show();
        $('#passwordHint').text('Minimum 6 characters.');
        $('#cardTitle').text('Add New Admin');
    }

    $('#newBtn').on('click', function () {
        clearForm();
        $('#addThisFormContainer').slideDown(300);
        $('#newBtn').hide();
    });

    $('#cancelBtn').on('click', function () {
        $('#addThisFormContainer').slideUp(200);
        $('#newBtn').show();
        clearForm();
    });

    $('#saveBtn').on('click', function () {
        var id = $('#adminId').val();
        var url = id ? '/admin/admins/' + id : '{{ route("admin.admins.store") }}';

        var fd = new FormData();
        fd.append('first_name', $('#first_name').val());
        fd.append('last_name', $('#last_name').val());
        fd.append('email', $('#email').val());

        var password = $('#password').val();
        if (password) {
            fd.append('password', password);
        }

        if (id) {
            fd.append('_method', 'PUT');
        }

        $.ajax({
            url: url, method: 'POST', data: fd, contentType: false, processData: false,
            success: function (res) {
                if (res.success) {
                    showSuccess(res.message);
                    $('#addThisFormContainer').slideUp(200);
                    $('#newBtn').show();
                    clearForm();
                    reloadTable('#admins-table');
                }
            },
            error: function (xhr) {
                let msg = xhr.responseJSON?.message ?? xhr.responseJSON?.errors?.[Object.keys(xhr.responseJSON.errors)[0]]?.[0] ?? 'Something went wrong.';
                showError(msg);
            }
        });
    });

    $(document).on('click', '.edit-btn', function () {
        var url = $(this).data('url');
        $.get(url, function (res) {
            if (!res.success) return showError('Could not load admin.');
            var d = res.data;
            clearForm();

            $('#adminId').val(d.id);
            $('#first_name').val(d.first_name);
            $('#last_name').val(d.last_name);
            $('#email').val(d.email);
            $('#passwordRequired').hide();
            $('#passwordHint').text('Leave blank to keep current password.');

            $('#cardTitle').text('Edit Admin');
            $('#addThisFormContainer').slideDown(300);
            $('#newBtn').hide();
            pageTop();
        });
    });

    // Delete handled by global .deleteBtn handler in custom.js

});
</script>
@endsection