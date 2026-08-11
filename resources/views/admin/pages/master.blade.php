<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light" data-sidebar="dark"
    data-sidebar-size="lg">

<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="{{ asset('resources/backend/js/layout.js') }}"></script>
    <link href="{{ asset('resources/backend/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('resources/backend/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('resources/backend/css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('resources/backend/css/custom.min.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

<body>

    <div id="layout-wrapper">

        @include('admin.partials.header')

        @include('admin.partials.sidebar')

        @include('admin.partials.confirmDelete')

        <div class="vertical-overlay"></div>

        <div class="main-content">
            <section class="page-content">
                @yield('content')
            </section>
        </div>

        <footer class="footer d-none">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> &copy;
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
    <script src="{{ asset('resources/backend/libs/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('resources/backend/js/app.js') }}"></script>
    <script src="{{ asset('resources/backend/js/custom.js') }}"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(function () {
            @if(session('success'))
                Swal.fire({ icon: 'success', title: '{{ session('success') }}', timer: 3000, showConfirmButton: false });
            @endif
            @if(session('error'))
                Swal.fire({ icon: 'error', title: '{{ session('error') }}', timer: 3000, showConfirmButton: false });
            @endif
        });
    </script>

    @yield('script')
</body>

</html>
