@php
    $user = auth()->user();
    $unreadSubmissions = $user->isAdmin() ? \App\Models\Submission::query()->whereNull('read_at')->count() : 0;
    $adminContent = [
        'admin.pages' => ['ph-files', "Pagina's"],
        'admin.posts' => ['ph-newspaper-clipping', 'Blogs'],
        'admin.units' => ['ph-cube', 'Units'],
        'admin.unit-terms' => ['ph-tag', 'Unit-kenmerken'],
        'admin.used-listings' => ['ph-recycle', 'Gebruikte units'],
        'admin.products' => ['ph-package', 'Producten'],
        'admin.vacancies' => ['ph-briefcase', 'Vacatures'],
        'admin.team-members' => ['ph-users-three', 'Team'],
        'admin.media' => ['ph-image', 'Media'],
    ];
    $adminSettings = [
        'admin.menu-items' => ['ph-list', "Menu's"],
        'admin.redirects' => ['ph-arrow-bend-up-right', 'Redirects'],
        'admin.users' => ['ph-user-gear', 'Gebruikers'],
        'admin.settings' => ['ph-gear-six', 'Instellingen'],
    ];
@endphp
<aside class="backend-sidebar">
    <div class="backend-sidebar__head">
        <a href="{{ url('/') }}/" class="backend-sidebar__brand" aria-label="{{ \App\Models\Setting::get('site_name') }}">
            <img src="{{ asset('images/logo.svg') }}" alt="{{ \App\Models\Setting::get('site_name') }}" class="backend-sidebar__brand-logo">
        </a>
    </div>

    <nav class="backend-sidebar__nav" aria-label="Backend menu">
        <a href="{{ route('dashboard') }}" class="backend-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"><i class="ph ph-house"></i><span>Dashboard</span></a>
        <a href="{{ route('quote.show') }}/" class="backend-link"><i class="ph ph-shopping-bag"></i><span>Mijn offerte</span></a>
        <a href="{{ route('my-used-listings.index') }}" class="backend-link {{ request()->routeIs('my-used-listings.*') ? 'is-active' : '' }}"><i class="ph ph-recycle"></i><span>Mijn units</span></a>
        <a href="{{ route('unit-composer') }}" class="backend-link {{ request()->routeIs('unit-composer') ? 'is-active' : '' }}"><i class="ph ph-cube-focus"></i><span>Unit samenstellen</span></a>
        <a href="{{ route('account.edit') }}" class="backend-link {{ request()->routeIs('account.*') ? 'is-active' : '' }}"><i class="ph ph-user-circle"></i><span>Account</span></a>

        @if ($user->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="backend-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}"><i class="ph ph-squares-four"></i><span>Beheer</span></a>
            <a href="{{ route('admin.submissions.index') }}" class="backend-link {{ request()->routeIs('admin.submissions.*') ? 'is-active' : '' }}"><i class="ph ph-tray"></i><span>Inzendingen{{ $unreadSubmissions ? ' ('.$unreadSubmissions.')' : '' }}</span></a>
            <a href="{{ route('admin.opdrachtgevers.index') }}" class="backend-link {{ request()->routeIs('admin.opdrachtgevers.*') ? 'is-active' : '' }}"><i class="ph ph-address-book"></i><span>Opdrachtgevers</span></a>

            <div class="backend-nav-group">
                <div class="backend-nav-group__title">Content</div>
                @foreach ($adminContent as $routePrefix => [$icon, $label])
                    <a href="{{ route($routePrefix.'.index') }}" class="backend-sublink {{ request()->routeIs($routePrefix.'.*') ? 'is-active' : '' }}"><i class="ph {{ $icon }}"></i><span>{{ $label }}</span></a>
                @endforeach
                <a href="{{ route('admin.unit-composer') }}" class="backend-sublink {{ request()->routeIs('admin.unit-composer') ? 'is-active' : '' }}"><i class="ph ph-flask"></i><span>Unit samenstellen (test)</span></a>
            </div>

            <div class="backend-nav-group">
                <div class="backend-nav-group__title">Website</div>
                @foreach ($adminSettings as $routePrefix => [$icon, $label])
                    <a href="{{ route($routePrefix.'.index') }}" class="backend-sublink {{ request()->routeIs($routePrefix.'.*') ? 'is-active' : '' }}"><i class="ph {{ $icon }}"></i><span>{{ $label }}</span></a>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="backend-sidebar__foot">
        <form method="POST" action="{{ request()->routeIs('admin.*') ? route('admin.logout') : route('logout') }}">
            @csrf
            <button type="submit" class="backend-logout"><i class="ph ph-sign-out"></i><span>Uitloggen</span></button>
        </form>
    </div>
</aside>
