@extends('admin.pages.master')
@section('title', 'Automation Engine')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Automation Engine</h4>
                    <p class="text-muted mb-0">Create rule-based workflows for reminders, tasks, communications and internal operations.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-history-line"></i> Run history</button>
                    <button class="btn btn-primary btn-sm"><i class="ri-add-line"></i> New Automation</button>
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
                            <p class="text-muted mb-2 text-truncate">Active Rules</p>
                            <h4 class="mb-0">—</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-flashlight-line"></i></span>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted fs-13">0 runs this month</p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Success Rate</p>
                            <h4 class="mb-0">—</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-checkbox-circle-line"></i></span>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted fs-13">No data yet</p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Time Saved</p>
                            <h4 class="mb-0">—</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-time-line"></i></span>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted fs-13">Estimated</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Automation Rules</h4>
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-add-line"></i> Build automation</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Trigger</th>
                                    <th>Conditions</th>
                                    <th>Actions</th>
                                    <th>Safety</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No automation rules configured yet</td>
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
                    <h4 class="card-title mb-0">Build Automation</h4>
                    <span class="badge bg-soft-secondary fs-12">Placeholder</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Trigger</label>
                            <input type="text" class="form-control" placeholder="e.g. Passport expires in 90 days" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Conditions</label>
                            <input type="text" class="form-control" placeholder="e.g. Plan is Premium" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Actions</label>
                            <input type="text" class="form-control" placeholder="e.g. Create renewal task" disabled>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary btn-sm" disabled><i class="ri-add-line"></i> Create Rule</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
