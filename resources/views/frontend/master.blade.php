<!DOCTYPE html>
<html lang="en">

@php
    $company = App\Models\CompanyDetails::firstOrCreate();
@endphp

<head>
    <meta charset="utf-8">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="language" content="English">

    <meta name="author" content="{{ $company->company_name ?? '' }}">

    <meta name="revisit-after" content="7 days">

    <link rel="alternate" hreflang="en-US" href="{{ url()->current() }}">

    <link rel="canonical" href="{{ url()->current() }}">

    {!! SEOMeta::generate() !!}
    {!! OpenGraph::generate() !!}
    {!! Twitter::generate() !!}

    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@type": "LocalBusiness",
            "name": "{{ $company->company_name ?? config('app.name') }}",
            "image": "{{ $company->logo ? asset('uploads/company/' . $company->logo) : '' }}",
            "description": "{{ $company->meta_description ?? '' }}",
            "url": "{{ url('/') }}",
            "telephone": "{{ $company->phone1 ?? '' }}",
            "email": "{{ $company->email ?? '' }}",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "{{ $company->address1 ?? '' }}",
                "addressLocality": "{{ $company->city ?? '' }}",
                "postalCode": "{{ $company->postcode ?? '' }}",
                "addressCountry": "BD"
            }
        }
    </script>

    @yield('ld-json')

    @if ($company->google_site_verification)
        <meta name="google-site-verification" content="{{ $company->google_site_verification }}">
    @endif

    @if ($company->google_analytics_id)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $company->google_analytics_id }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());
            gtag('config', '{{ $company->google_analytics_id }}');
        </script>
    @endif

    @if ($company->google_tag_manager_id)
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '{{ $company->google_tag_manager_id }}');
        </script>
    @endif

    @if ($company->facebook_pixel_id)
        <script>
            ! function(f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function() {
                    n.callMethod ?
                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window,
                document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ $company->facebook_pixel_id }}');
            fbq('track', 'PageView');
        </script>
    @endif

    <link rel="icon" href="{{ asset('uploads/company/' . $company->fav_icon) }}" sizes="48x48">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link href="{{ asset('resources/frontend/css/style.css') }}" rel="stylesheet">

    @yield('style')

</head>

<body>

    @include('frontend.header')

    <div id="flash-msg"></div>

    <main @spaContent>

        @yield('content')

    </main>

    @include('frontend.footer')

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="{{ asset('resources/frontend/js/app.js') }}"></script>

    @spaEngine

    @yield('script')

</body>

</html>