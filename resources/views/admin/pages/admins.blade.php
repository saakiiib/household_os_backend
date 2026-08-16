@extends('admin.pages.master')
@section('title', 'Admins')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Admins</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class="ri-user-add-line"></i> Add new admin
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Admins</p>
                            <h4 class="mb-0">{{ number_format($totalAdmins) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-shield-user-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">All Admins</h4>
                    <span class="badge bg-soft-primary fs-12">{{ number_format($totalAdmins) }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="admins-table" class="table table-hover table-centered align-middle mb-0" style="width:100%">
                            <thead class="table-light">
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

<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.admins.store') }}" id="addAdminForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add new admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">First name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <small class="text-muted">Minimum 8 characters. They can log in with this email and password.</small>
                    </div>
                    @if($errors->any())
                        <div class="alert alert-danger py-2 mb-0">{{ $errors->first() }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create admin</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$(function () {
    const table = $('#admins-table').DataTable({
        processing: true,
        serverSide: true,
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

    // Remove admin (AJAX, no full reload)
    $(document).on('click', '.remove-admin', function () {
        const btn = $(this);
        const id = btn.data('id');
        if (!confirm('Remove admin access for this person?')) return;

        btn.prop('disabled', true);
        fetch("{{ route('admin.admins.destroy', '__ID__') }}".replace('__ID__', id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams({ _method: 'DELETE' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: data.message, timer: 2500, showConfirmButton: false });
                table.ajax.reload(null, false);
            } else {
                Swal.fire({ icon: 'error', title: data.message || 'Action failed', timer: 3000, showConfirmButton: false });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Action failed', timer: 3000, showConfirmButton: false }))
        .finally(() => btn.prop('disabled', false));
    });

    // Add new admin (AJAX, no full reload)
    $('#addAdminForm').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        fetch("{{ route('admin.admins.store') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(form)
        })
        .then(async r => {
            if (!r.ok) throw await r.json();
            return r.json();
        })
        .then(data => {
            Swal.fire({ icon: 'success', title: data.message, timer: 2500, showConfirmButton: false });
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('addAdminModal')).hide();
            table.ajax.reload(null, false);
        })
        .catch(err => {
            const msg = err && err.errors ? Object.values(err.errors).flat().join(' ') : 'Please check the form.';
            Swal.fire({ icon: 'error', title: msg, timer: 4000, showConfirmButton: false });
        });
    });
});
</script>
@endsection
