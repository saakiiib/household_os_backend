@extends('admin.pages.master')
@section('title', 'Reports & Exports')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Reports & Exports</h4>
                    <p class="text-muted mb-0">Generate scheduled or on-demand PDF, Excel and CSV reports.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-upload-2-line"></i> Import</button>
                    <button class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Create Report</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Scheduled Reports</p>
                            <h4 class="mb-0">0</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-calendar-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Available Formats</p>
                            <h4 class="mb-0">PDF · XLSX · CSV</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-file-chart-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Last Export</p>
                            <h4 class="mb-0">—</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-download-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">Reports</h4>
                    <span class="badge bg-soft-primary fs-12">0 reports</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Report</th>
                                    <th>Category</th>
                                    <th>Schedule</th>
                                    <th>Recipients</th>
                                    <th>Last Run</th>
                                    <th>Format</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No reports configured yet</td>
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
                <div class="card-header d-flex align-items-center">
                    <h4 class="card-title mb-0">Quick Exports</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Export Households (CSV)</a>
                        <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Export Payments (XLSX)</a>
                        <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Export Users (CSV)</a>
                        <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Audit Log (PDF)</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
