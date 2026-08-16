@extends('admin.pages.master')
@section('title', 'Storage Explorer')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Storage Explorer</h4>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mb-4" role="status">
        <i class="ri-information-line me-1"></i>
        <strong>Demo page.</strong> This is a static preview and is not connected to live data.
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="ri-hard-drive-2-line fs-36 d-block mb-2"></i>
                    <p class="mb-0">Storage Explorer is shown here as a demo. Full functionality is not available yet.</p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
