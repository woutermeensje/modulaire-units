{{-- Huur- en koopagina's (/huren/..., /kopen/...): opbouw overgenomen van de /inhuren/-landingspagina's van OnlineMarketingjobs.nl. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{section: string, heading: string, body: string, blocks: list<string>, image: ?string, counterpart: ?\App\Models\Page, related: \Illuminate\Support\Collection<int, \App\Models\Page>} $landing */
    $requestUrl = url('offerte-aanvragen').'/';
    $phone = \App\Models\Setting::get('phone');
    $email = \App\Models\Setting::get('email');
    $isRental = $landing['section'] === 'huren';
    $arrowIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->slug.' mh-offer-page')

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
<main id="content" class="mh-offer" aria-labelledby="mh-offer-title">
    <header class="mh-offer-hero" @if ($landing['image']) style="--mh-offer-hero-image: url('{{ $landing['image'] }}');" @endif>
        <div class="mh-offer-hero__panel">
            <div class="mh-offer-hero__content">
                <h1 id="mh-offer-title">{{ $page->title }}</h1>
                <p class="mh-offer-hero__lead">{{ $page->hero['subtitle'] ?? 'Bekijk de mogelijkheden en vraag vrijblijvend informatie aan bij '.\App\Models\Setting::get('site_name').'.' }}</p>
                <div class="mh-offer-hero__actions">
                    <a class="mh-offer-btn mh-offer-btn--primary" href="{{ $requestUrl }}">Informatie aanvragen</a>
                </div>
            </div>
        </div>
        <div class="mh-offer-hero__image" aria-hidden="true"></div>
    </header>

    <div class="mh-offer-overlap">
        <article class="mh-offer-intro">
            {{-- Vaste introductie, bovenaan elke huur- en koopagina. --}}
            <p class="mh-offer-intro__platform">{{ \App\Models\Setting::get('footer_about') }}</p>

            <h2 class="mh-offer-intro__heading">{{ $landing['heading'] }}</h2>

            <div class="mh-offer-intro__body">
                {!! $landing['body'] !!}
            </div>

            {{-- Vaste afsluiting, onderaan elke huur- en koopagina. --}}
            <h2 class="mh-offer-intro__heading mh-offer-intro__heading--sub">Hoe nu verder?</h2>
            <p class="mh-offer-intro__platform mh-offer-intro__platform--bottom">
                Heb je vragen over de mogelijkheden, {{ $isRental ? 'de huurperiode' : 'de levertijd' }} of de kosten? <a href="{{ $requestUrl }}">Vraag vrijblijvend informatie aan</a>@if ($phone), bel ons op <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif @if ($email) of stuur een e-mail naar <a href="mailto:{{ $email }}">{{ $email }}</a>@endif.
            </p>
        </article>

        <aside class="mh-offer-request" aria-label="Mogelijkheden">
            <h2>Mogelijkheden</h2>

            <ul class="mh-offer-contact">
                @if ($phone)
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>
                    </li>
                @endif
                @if ($email)
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                        <a href="mailto:{{ $email }}">{{ $email }}</a>
                    </li>
                @endif
            </ul>

            <div class="mh-offer-options">
                @if ($landing['counterpart'])
                    <a class="mh-offer-option" href="{{ $landing['counterpart']->url() }}">
                        <span class="mh-offer-option__meta">{{ $isRental ? 'Kopen' : 'Huren' }}</span>
                        <strong>{{ $landing['counterpart']->title }}</strong>
                        <span>{{ $isRental ? 'Liever zelf eigenaar? Bekijk de mogelijkheden om te kopen.' : 'Liever flexibel en zonder investering? Bekijk de mogelijkheden om te huren.' }}</span>
                    </a>
                @endif

                <a class="mh-offer-option" href="{{ url('transport-plaatsing') }}/">
                    <span class="mh-offer-option__meta">Service</span>
                    <strong>Transport &amp; plaatsing</strong>
                    <span>{{ $isRental ? 'Wij leveren, plaatsen en halen de units weer op na afloop van de huurperiode.' : 'Wij leveren de units en plaatsen ze direct op jouw locatie.' }}</span>
                </a>

                <a class="mh-offer-option" href="{{ url('inrichting') }}/">
                    <span class="mh-offer-option__meta">Maatwerk</span>
                    <strong>Inrichting</strong>
                    <span>Units volledig ingericht en afgestemd op jouw project of organisatie.</span>
                </a>

                <a class="mh-offer-option mh-offer-option--highlight" href="{{ $requestUrl }}">
                    <span class="mh-offer-option__meta">Gratis</span>
                    <strong>Advies op maat</strong>
                    <span>Een adviseur denkt met je mee en stuurt een vrijblijvende offerte.</span>
                </a>
            </div>

            <div class="mh-offer-request__actions">
                <a class="mh-offer-btn mh-offer-btn--primary" href="{{ $requestUrl }}">Informatie aanvragen</a>
            </div>
        </aside>
    </div>

    @foreach ($landing['blocks'] as $block)
        <section class="mh-offer-block">{!! $block !!}</section>
    @endforeach

    @if ($landing['related']->isNotEmpty())
        <section class="mh-offer-related" aria-labelledby="mh-offer-related-title">
            <h2 id="mh-offer-related-title">{{ $page->path === $landing['section'] ? 'Alle mogelijkheden:' : 'Bekijk ook:' }}</h2>
            <div class="mh-offer-related__grid">
                @foreach ($landing['related'] as $relatedPage)
                    <a class="mh-offer-related-card" href="{{ $relatedPage->url() }}">
                        <strong>{{ $relatedPage->title }}</strong>
                        {!! $arrowIcon !!}
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
