<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light" data-sidebar="dark"
    data-sidebar-size="lg">

@php
    $company = App\Models\CompanyDetails::firstOrCreate();
@endphp

<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company->company_name ?? '' }} - @yield('title')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <link href="{{ asset('uploads/company/' . $company->fav_icon) }}" rel="icon">

    <!-- Layout config Js -->
    <script src="{{ asset('resources/backend/js/layout.js') }}"></script>

    <!-- Bootstrap Css -->
    <link href="{{ asset('resources/backend/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons Css -->
    <link href="{{ asset('resources/backend/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App Css -->
    <link href="{{ asset('resources/backend/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">

    <link href="{{ asset('resources/backend/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet"
        type="text/css" />

    <!-- Custom Css -->
    <link href="{{ asset('resources/backend/css/custom.min.css') }}" rel="stylesheet" type="text/css" />
</head>

<body>

    <div id="layout-wrapper">

        @include('admin.partials.header')

        @include('admin.partials.sidebar')

        @include('admin.partials.confirmDelete')

        <div class="vertical-overlay"></div>
        
        <div class="main-content">
            <section class="page-content" @spaContent>
                @yield('content')
            </section>
        </div>

        <footer class="footer d-none">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>
                            document.write(new Date().getFullYear())
                        </script> ©
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">
                            Design & Develop by
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top">
        <i class="ri-arrow-up-line"></i>
    </button>

    <script src="{{ asset('resources/backend/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('resources/backend/libs/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('resources/backend/libs/feather-icons/feather.min.js') }}"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>

    <script src="{{ asset('resources/backend/libs/sweetalert2/sweetalert2.min.js') }}"></script>

    <script src="{{ asset('resources/backend/js/app.js') }}"></script>

    <script src="{{ asset('resources/backend/js/custom.js') }}"></script>

    <script>
        $(function () {
            @if(session('success'))
                showSuccess("{{ session('success') }}");
            @endif

            @if(session('error'))
                showError("{{ session('error') }}");
            @endif
        });
    </script>

    @spaEngine

    @yield('script')
</body>

</html>