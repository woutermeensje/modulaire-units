@php
    $mainMenu = \App\Models\MenuItem::tree('main');
    $currentPath = request()->path();
    $quoteCount = app(\App\Services\QuoteCart::class)->count();
@endphp
<header id="mh-header" class="mh-header" role="banner">
    <div class="mh-header__inner">
        <div class="mh-header__brand">
            <a href="{{ url('/') }}/" class="mh-header__logo-link" aria-label="{{ \App\Models\Setting::get('site_name') }}">
                <img src="{{ asset('images/logo.svg') }}" alt="{{ \App\Models\Setting::get('site_name') }}" class="mh-header__logo-image" width="800" height="200">
            </a>
        </div>

        <nav class="mh-header__nav" aria-label="Primaire navigatie">
            <ul id="menu-hoofdmenu" class="mh-nav__list">
                @foreach ($mainMenu as $item)
                    <li @class(['mh-nav__item', 'mh-nav__item--has-children' => $item->children->isNotEmpty()])>
                        <a @class(['mh-nav__link', 'is-active' => $item->isActive($currentPath)]) href="{{ $item->url }}">{{ $item->label }}@if ($item->children->isNotEmpty())<span class="mh-nav__chev" aria-hidden="true"></span>@endif</a>
                        @if ($item->children->isNotEmpty())
                            <ul class="mh-nav__dropdown">
                                @foreach ($item->children as $child)
                                    <li class="mh-nav__item"><a @class(['mh-nav__link', 'is-active' => $child->isActive($currentPath)]) href="{{ $child->url }}">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="mh-header__divider"></div>

        <div class="mh-header__cta">
            @auth
                <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="mh-header__login">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="mh-header__login">Inloggen</a>
            @endauth
            <a href="{{ url('offerte-aanvragen') }}/" class="mh-btn mh-btn--solid mh-btn--nav-contact">Informatie aanvragen</a>
        </div>

        <a href="{{ route('quote.show') }}/" class="mh-header__quote-icon mh-header__quote-icon--always" aria-label="Mijn offerte">
            <svg class="mh-header__quote-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 10h14" />
                <path d="M6 10l1.25 8.5A2 2 0 0 0 9.23 20h5.54a2 2 0 0 0 1.98-1.5L18 10" />
                <path d="M9 10V8a3 3 0 0 1 6 0v2" />
            </svg>
            @if ($quoteCount > 0)
                <span class="mh-header__quote-badge">{{ $quoteCount }}</span>
            @endif
        </a>

        <button class="mh-header__hamburger" aria-label="Menu openen" aria-expanded="false" aria-controls="mh-mobile-nav">
            <span class="mh-hamburger__bar"></span>
            <span class="mh-hamburger__bar"></span>
            <span class="mh-hamburger__bar"></span>
        </button>
    </div>
</header>

<div id="mh-mobile-nav" class="mh-mobile-nav" aria-hidden="true">
    <div class="mh-mobile-nav__panel">
        <button class="mh-mobile-nav__close" aria-label="Menu sluiten">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <ul id="menu-hoofdmenu-1" class="mh-mobile-nav__list">
            @foreach ($mainMenu as $item)
                <li @class(['menu-item', 'menu-item-has-children' => $item->children->isNotEmpty(), 'current-menu-item' => $item->isActive($currentPath)])>
                    <a href="{{ $item->url }}">{{ $item->label }}</a>
                    @if ($item->children->isNotEmpty())
                        <ul class="sub-menu">
                            @foreach ($item->children as $child)
                                <li @class(['menu-item', 'current-menu-item' => $child->isActive($currentPath)])><a href="{{ $child->url }}">{{ $child->label }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mh-mobile-nav__divider"></div>
        <div class="mh-mobile-nav__ctas">
            <a href="{{ route('quote.show') }}/" class="mh-btn mh-btn--outline mh-btn--has-badge mh-btn--nav-quote mh-mobile-nav__cta">
                <span>Mijn offerte</span>
                @if ($quoteCount > 0)
                    <span class="mh-nav-badge">{{ $quoteCount }}</span>
                @endif
            </a>
            @auth
                <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="mh-btn mh-btn--outline mh-mobile-nav__cta">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="mh-btn mh-btn--outline mh-mobile-nav__cta">Inloggen</a>
            @endauth
            <a href="{{ url('offerte-aanvragen') }}/" class="mh-btn mh-btn--solid mh-btn--nav-contact mh-mobile-nav__cta">Informatie aanvragen</a>
        </div>
    </div>
</div>

<div class="mh-cta-bar" id="mh-cta-bar" role="complementary" aria-label="Hulp nodig?">
    <div class="mh-cta-bar__inner">
        <p class="mh-cta-bar__text">{{ \App\Models\Setting::get('cta_bar_text') }}</p>
        <div class="mh-cta-bar__actions">
            <a href="{{ url('offerte-aanvragen') }}/" class="mh-btn mh-btn--accent mh-cta-bar__btn">Neem contact op</a>
        </div>
        <button class="mh-cta-bar__close" id="mh-cta-bar-close" aria-label="Balk sluiten">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
</div>
