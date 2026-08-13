@extends('admin.pages.master')
@section('title', 'Platform Settings')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Platform Settings</h4>
                    <p class="text-muted mb-0">Central configuration for company, messaging, payments, storage, SEO, backups and legal content.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-secondary btn-sm"><i class="ri-eye-line"></i> View changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h4 class="card-title mb-0">Configuration Health</h4>
                    <span class="badge bg-soft-warning text-warning ms-auto">Partial</span>
                </div>
                <div class="card-body">
                    <div class="vstack gap-3">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">Stripe</span>
                            <span class="text-success fw-medium"><i class="ri-checkbox-circle-line"></i> Connected</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">Firebase</span>
                            <span class="text-success fw-medium"><i class="ri-checkbox-circle-line"></i> Connected</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">Mail Provider</span>
                            <span class="text-success fw-medium"><i class="ri-checkbox-circle-line"></i> Connected</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">SMS Provider</span>
                            <span class="text-warning fw-medium"><i class="ri-error-warning-line"></i> Test mode</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">Object Storage</span>
                            <span class="text-success fw-medium"><i class="ri-checkbox-circle-line"></i> Encrypted</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h4 class="card-title mb-0">General</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Platform name</label>
                            <input type="text" class="form-control" value="Household OS" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Support email</label>
                            <input type="email" class="form-control" value="support@household.os" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default timezone</label>
                            <input type="text" class="form-control" value="Europe/London" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default currency</label>
                            <input type="text" class="form-control" value="GBP (£)" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Company address</label>
                            <input type="text" class="form-control" value="Milton Keynes, United Kingdom" disabled>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary btn-sm" disabled><i class="ri-save-line"></i> Save Settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h4 class="card-title mb-0">Billing & Notifications</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Payments provider</label>
                            <input type="text" class="form-control" value="Stripe" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email notifications</label>
                            <input type="text" class="form-control" value="Enabled" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SMS notifications</label>
                            <input type="text" class="form-control" value="Test mode" disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h4 class="card-title mb-0">Security</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Two-factor authentication</label>
                            <input type="text" class="form-control" value="Enforced for admins" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Session timeout</label>
                            <input type="text" class="form-control" value="30 minutes" disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
