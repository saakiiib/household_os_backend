<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg">

<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="{{ asset('resources/backend/js/layout.js') }}"></script>

    <link href="{{ asset('resources/backend/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />

    <link href="{{ asset('resources/backend/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <link href="{{ asset('resources/backend/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    <link href="{{ asset('resources/backend/css/custom.min.css') }}" rel="stylesheet" type="text/css" />
</head>

<body>
    <div class="auth-page-wrapper pt-5">
        <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
            <div class="bg-overlay"></div>
            <div class="shape">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120">
                    <path d="M0,36 C144,53.6 432,123.2 720,124 C1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
                </svg>
            </div>
        </div>

        <div class="auth-page-content">
            <div class="container">
                @yield('content')
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="{{ asset('resources/backend/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('resources/backend/libs/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('resources/backend/js/pages/password-addon.init.js') }}"></script>

</body>
</html>