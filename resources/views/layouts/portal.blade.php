{{--
    Klantomgeving en beheer. Markup en styling zijn 1-op-1 overgenomen van de dashboards
    in de inlogomgeving (backend-shell, backend-sidebar, portal-page, sales-card ...).
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') - {{ \App\Models\Setting::get('site_name') }}</title>
    <meta name="theme-color" content="#0a6b8d">

    <link rel="icon" href="{{ asset('images/favicon-32x32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180x180.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Work+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&amp;display=swap&amp;subset=latin">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

    @vite(['resources/css/portal.css', 'resources/js/portal.js'])
    @stack('styles')
</head>
<body class="app">
    <div class="app-shell">
        @auth
            <div class="backend-shell" data-backend-shell>
                @include('portal.partials.sidebar')

                <div class="backend-content-wrap">
                    <header class="backend-topbar">
                        <div class="backend-topbar__inner">
                            <button type="button" class="backend-topbar__menu-btn" data-backend-toggle aria-label="Open menu">Menu</button>
                            <div class="backend-topbar__identity">
                                <span class="backend-topbar__welcome">Welkom, {{ auth()->user()->firstName() }}!</span>
                            </div>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.units.create') }}" class="btn btn-primary backend-topbar__action"><i class="ph ph-plus-circle"></i><span>Unit toevoegen</span></a>
                            @else
                                <a href="{{ url('offerte-aanvragen') }}/" class="btn btn-primary backend-topbar__action"><i class="ph ph-chat-circle-text"></i><span>Informatie aanvragen</span></a>
                            @endif
                        </div>
                    </header>

                    <main class="app-main backend-main">
                        @yield('content')
                    </main>
                </div>
            </div>
        @else
            <main class="app-main auth-shell">
                <a href="{{ url('/') }}/" class="auth-shell__brand" aria-label="{{ \App\Models\Setting::get('site_name') }}">
                    <img src="{{ asset('images/logo.svg') }}" alt="{{ \App\Models\Setting::get('site_name') }}">
                </a>
                @yield('content')
                <a href="{{ url('/') }}/" class="auth-shell__back">&larr; Terug naar de website</a>
            </main>
        @endauth
    </div>
    @stack('scripts')
</body>
</html>
