@extends('admin.pages.master')
@section('title', 'Media Library')
@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0">Media Library</h4>
                    <p class="text-muted mb-0">Manage reusable images, videos, documents and brand assets</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-upload-line"></i> Import</a>
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Upload Asset</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Media Assets</h4>
                    <span class="badge bg-soft-secondary text-secondary fs-12">0 total</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <input type="text" class="form-control" placeholder="Search..." disabled>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-select" disabled>
                                <option>All statuses</option>
                                <option>Active</option>
                                <option>Pending</option>
                                <option>Archived</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <input type="date" class="form-control" disabled>
                        </div>
                        <div class="col-sm-2 d-flex gap-2">
                            <button class="btn btn-soft-secondary btn-sm flex-grow-1" disabled>More filters</button>
                            <button class="btn btn-soft-primary btn-sm flex-grow-1" disabled>Export</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset</th>
                                    <th>Type</th>
                                    <th>Dimensions</th>
                                    <th>Size</th>
                                    <th>Used In</th>
                                    <th>Uploaded By</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No data yet</td>
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
