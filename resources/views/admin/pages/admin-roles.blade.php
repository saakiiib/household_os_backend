@extends('admin.pages.master')
@section('title', 'Admin Roles & Permissions')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Admin Roles & Permissions</h4>
                    <p class="text-muted mb-0">Enterprise RBAC for super admins, finance, support, content, security and custom roles.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-upload-2-line"></i> Import</button>
                    <button class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Create Role</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Roles</h4>
                    <span class="badge bg-soft-primary fs-12">0 roles</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Admins</th>
                                    <th>Scope</th>
                                    <th>Sensitive Actions</th>
                                    <th>Last Updated</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No roles defined yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Permission Matrix</h4>
                    <span class="badge bg-soft-secondary fs-12">Placeholder</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Permission</th>
                                    <th>Super Admin</th>
                                    <th>Finance</th>
                                    <th>Support</th>
                                    <th>Content</th>
                                    <th>Security</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Permission matrix will appear here once roles are configured</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
