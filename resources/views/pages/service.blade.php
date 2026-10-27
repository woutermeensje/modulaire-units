{{-- Dienstpagina's (template "service"), bijv. /transport-plaatsing/: hero, afwisselende tekst/foto-blokken en een contactbalk. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{title: string, intro: string, image: ?string, imageAlt: string, blocks: list<array{heading: ?string, body: string, image: ?string, alt: string}>} $service */
    $requestUrl = url('offerte-aanvragen').'/';
    $phone = \App\Models\Setting::get('phone');
    $email = \App\Models\Setting::get('email');
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->slug.' mh-service-page')

@section('content')
<main id="content" class="mh-offer mh-service" aria-labelledby="mh-service-title">
    <header class="mh-offer-hero" @if ($service['image']) style="--mh-offer-hero-image: url('{{ url($service['image']) }}');" @endif>
        <div class="mh-offer-hero__panel">
            <div class="mh-offer-hero__content">
                <h1 id="mh-service-title">{{ $service['title'] }}</h1>
                @if ($service['intro'] !== '')
                    <div class="mh-offer-hero__lead mh-service__intro">{!! $service['intro'] !!}</div>
                @endif
                <div class="mh-offer-hero__actions">
                    <a class="mh-offer-btn mh-offer-btn--primary" href="{{ $requestUrl }}">Informatie aanvragen</a>
                </div>
            </div>
        </div>
        <div class="mh-offer-hero__image" aria-hidden="true"></div>
    </header>

    @if ($service['blocks'] !== [])
        <div class="mh-service__blocks">
            @foreach ($service['blocks'] as $block)
                <section @class(['mh-service-block', 'mh-service-block--reverse' => $loop->even, 'mh-service-block--text-only' => ! $block['image']])>
                    @if ($block['image'])
                        <figure class="mh-service-block__media">
                            <img src="{{ $block['image'] }}" alt="{{ $block['alt'] }}" loading="lazy" decoding="async">
                        </figure>
                    @endif
                    <div class="mh-service-block__content">
                        <span class="mh-service-block__step" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        @if ($block['heading'])
                            <h2 class="mh-service-block__title">{{ $block['heading'] }}</h2>
                        @endif
                        <div class="mh-service-block__body text-content">{!! $block['body'] !!}</div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <section class="mh-service-cta" aria-labelledby="mh-service-cta-title">
        <div class="mh-service-cta__inner">
            <div>
                <h2 id="mh-service-cta-title" class="mh-service-cta__title">Vragen over {{ \Illuminate\Support\Str::lower($page->title) }}?</h2>
                <p class="mh-service-cta__text">
                    Wij denken graag met je mee.
                    @if ($phone) Bel <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif
                    @if ($email) of mail naar <a href="mailto:{{ $email }}">{{ $email }}</a>.@endif
                </p>
            </div>
            <a class="mh-offer-btn mh-offer-btn--primary mh-service-cta__btn" href="{{ $requestUrl }}">Informatie aanvragen</a>
        </div>
    </section>
</main>
@endsection
