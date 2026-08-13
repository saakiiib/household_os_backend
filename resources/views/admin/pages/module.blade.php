@php
    $icon = $icon ?? 'ri-apps-2-line';
    $desc = $desc ?? 'This section is part of the Household OS admin. The backend module is not connected yet, so live data will appear here once it is.';
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">{{ $title ?? 'Module' }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                        <li class="breadcrumb-item active">{{ $title ?? 'Module' }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1"><i class="{{ $icon }} me-2"></i>{{ $title ?? 'Module' }}</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">{{ $desc }}</p>
                    <div class="alert alert-info mb-0">
                        <i class="ri-information-line me-1"></i>
                        Wired into the admin navigation and built on the standard admin layout. Real-time data will render here once the corresponding API/module is connected.
                    </div>
                    @isset($slot){{ $slot }}@endisset
                </div>
            </div>
        </div>
    </div>
</div>
