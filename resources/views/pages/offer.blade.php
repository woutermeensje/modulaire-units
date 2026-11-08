{{-- Huur- en koopagina's (/huren/..., /kopen/...): opbouw overgenomen van de /inhuren/-landingspagina's van OnlineMarketingjobs.nl. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{section: string, heading: string, body: string, blocks: list<string>, image: ?string, counterpart: ?\App\Models\Page} $landing */
    /** @var array{configuration: array<string, mixed>} $composer */
    $requestUrl = url('offerte-aanvragen').'/';
    $phone = \App\Models\Setting::get('phone');
    $email = \App\Models\Setting::get('email');
    $isRental = $landing['section'] === 'huren';
    $heroTitle = $page->source('path') === $landing['section']
        ? match ($landing['section']) {
            'huren' => __('Modulaire Units Huren'),
            'kopen' => __('Modulaire Units Kopen'),
            default => $page->title,
        }
        : $page->title;
    $requestLink = '<a href="'.e($requestUrl).'">'.e(__('Vraag vrijblijvend informatie aan')).'</a>';
    $phoneLink = $phone ? '<a href="tel:'.e(preg_replace('/[^0-9+]/', '', $phone)).'">'.e($phone).'</a>' : '';
    $emailLink = $email ? '<a href="mailto:'.e($email).'">'.e($email).'</a>' : '';
    $followUp = match (true) {
        $phone && $email => __(':request, bel ons op :phone of stuur een e-mail naar :email.', ['request' => $requestLink, 'phone' => $phoneLink, 'email' => $emailLink]),
        (bool) $phone => __(':request of bel ons op :phone.', ['request' => $requestLink, 'phone' => $phoneLink]),
        (bool) $email => __(':request of stuur een e-mail naar :email.', ['request' => $requestLink, 'email' => $emailLink]),
        default => $requestLink.'.',
    };
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->source('slug').' mh-offer-page')

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
<main id="content" class="mh-offer" aria-labelledby="mh-offer-title">
    <header class="mh-offer-hero" @if ($landing['image']) style="--mh-offer-hero-image: url('{{ url($landing['image']) }}');" @endif>
        <div class="mh-offer-hero__panel">
            <div class="mh-offer-hero__content">
                <h1 id="mh-offer-title">{{ $heroTitle }}</h1>
                <p class="mh-offer-hero__lead">{{ $page->hero['subtitle'] ?? __('Bekijk de mogelijkheden en vraag vrijblijvend informatie aan bij :site.', ['site' => \App\Models\Setting::get('site_name')]) }}</p>
                <div class="mh-offer-hero__actions">
                    {{-- Via een account kunnen klanten straks zelf units uitzoeken. --}}
                    <a class="mh-offer-btn mh-offer-btn--primary" href="{{ route('register') }}">{{ __('Account aanmaken') }}</a>
                    <a class="mh-offer-btn mh-offer-btn--dark" href="#unit-samensteller">{{ __('Units samenstellen') }}</a>
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

            <div class="mh-offer-intro__body text-content">
                {!! $landing['body'] !!}
            </div>

            {{-- Vaste afsluiting, onderaan elke huur- en koopagina. --}}
            <h2 class="mh-offer-intro__heading mh-offer-intro__heading--sub">{{ __('Hoe nu verder?') }}</h2>
            <p class="mh-offer-intro__platform mh-offer-intro__platform--bottom">
                {{ $isRental ? __('Heb je vragen over de mogelijkheden, de huurperiode of de kosten?') : __('Heb je vragen over de mogelijkheden, de levertijd of de kosten?') }} {!! $followUp !!}
            </p>
        </article>

        <aside class="mh-offer-request" aria-label="{{ __('Mogelijkheden') }}">
            <h2>{{ __('Mogelijkheden') }}</h2>

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
                        <span class="mh-offer-option__meta">{{ $isRental ? __('Kopen') : __('Huren') }}</span>
                        <strong>{{ $landing['counterpart']->title }}</strong>
                        <span>{{ $isRental ? __('Liever zelf eigenaar? Bekijk de mogelijkheden om te kopen.') : __('Liever flexibel en zonder investering? Bekijk de mogelijkheden om te huren.') }}</span>
                    </a>
                @endif

                <a class="mh-offer-option" href="{{ url('transport-plaatsing') }}/">
                    <span class="mh-offer-option__meta">{{ __('Service') }}</span>
                    <strong>{{ __('Transport & plaatsing') }}</strong>
                    <span>{{ $isRental ? __('Wij leveren, plaatsen en halen de units weer op na afloop van de huurperiode.') : __('Wij leveren de units en plaatsen ze direct op jouw locatie.') }}</span>
                </a>

                <a class="mh-offer-option" href="{{ url('inrichting') }}/">
                    <span class="mh-offer-option__meta">{{ __('Maatwerk') }}</span>
                    <strong>{{ __('Inrichting') }}</strong>
                    <span>{{ __('Units volledig ingericht en afgestemd op jouw project of organisatie.') }}</span>
                </a>

                <a class="mh-offer-option mh-offer-option--highlight" href="{{ $requestUrl }}">
                    <span class="mh-offer-option__meta">{{ __('Gratis') }}</span>
                    <strong>{{ __('Advies op maat') }}</strong>
                    <span>{{ __('Een adviseur denkt met je mee en stuurt een vrijblijvende offerte.') }}</span>
                </a>
            </div>

            <div class="mh-offer-request__actions">
                <a class="mh-offer-btn mh-offer-btn--primary" href="{{ $requestUrl }}">{{ __('Informatie aanvragen') }}</a>
            </div>
        </aside>
    </div>

    @foreach ($landing['blocks'] as $block)
        <section class="mh-offer-block">{!! $block !!}</section>
    @endforeach

    @include('partials.unit-composer-section', ['composer' => $composer])
</main>
@endsection
