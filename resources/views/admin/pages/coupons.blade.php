@extends('admin.pages.master')
@section('title', 'Coupons & Promotions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0">Coupons & Promotions</h4>
                    <p class="text-muted mb-0">Manage campaigns, referral codes, trial extensions and conversion impact</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-upload-line"></i> Import</a>
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-add-line"></i> New Coupon</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" placeholder="Search..." disabled>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" disabled>
                                <option>All statuses</option>
                                <option>Active</option>
                                <option>Pending</option>
                                <option>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-soft-primary btn-sm w-100" disabled><i class="ri-filter-line"></i> Filter</button>
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
                    <h4 class="card-title mb-0 flex-grow-1">Coupons</h4>
                    <span class="badge bg-soft-secondary fs-12">0 total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Discount</th>
                                    <th>Redemptions</th>
                                    <th>Revenue Impact</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No coupons yet</td>
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
