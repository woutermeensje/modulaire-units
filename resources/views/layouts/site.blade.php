<!DOCTYPE html>
<html lang="nl-NL">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo', ['seo' => $seo ?? \App\Support\Seo::make('')])

    <link rel="icon" href="{{ asset('images/favicon-32x32.png') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('images/favicon-192x192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180x180.png') }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($customCss = trim($__env->yieldContent('custom_css')))
        <style>{!! $customCss !!}</style>
    @endif

    @if ($analyticsId = \App\Models\Setting::get('google_analytics_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($analyticsId));
        </script>
    @endif
</head>
<body class="elementor-kit-6 @yield('body_class')">
    <a class="skip-link screen-reader-text" href="#content">Ga naar de inhoud</a>

    @include('partials.header')

    @yield('content')

    @include('partials.footer')
</body>
</html>
