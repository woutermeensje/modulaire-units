{{-- Overzichtspagina van de modulaire units (/modulaire-units/): hero met schuin paneel, de pagina-inhoud en de gepubliceerde units die in het account zijn samengesteld. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{intro: string, content: string, image: ?string, units: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Unit>} $overview */
    $requestUrl = url('offerte-aanvragen').'/';
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->slug.' mh-unit-types-page')

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
<main id="content" class="mh-offer mh-unit-types" aria-labelledby="mh-unit-types-title">
    <header class="mh-offer-hero" @if ($overview['image']) style="--mh-offer-hero-image: url('{{ url($overview['image']) }}');" @endif>
        <div class="mh-offer-hero__panel">
            <div class="mh-offer-hero__content">
                <h1 id="mh-unit-types-title">{{ $page->title }}</h1>
                @if ($overview['intro'] !== '')
                    <p class="mh-offer-hero__lead">{{ $overview['intro'] }}</p>
                @endif
                <div class="mh-offer-hero__actions">
                    <a class="mh-offer-btn mh-offer-btn--primary" href="{{ $requestUrl }}">Informatie aanvragen</a>
                    @if ($overview['units']->isNotEmpty())
                        <a class="mh-offer-btn mh-offer-btn--dark" href="#units">Bekijk de units</a>
                    @endif
                </div>
            </div>
        </div>
        <div class="mh-offer-hero__image" aria-hidden="true"></div>
    </header>

    @if ($overview['content'] !== '')
        <div class="mh-unit-types__content text-content">{!! $overview['content'] !!}</div>
    @endif

    @if ($overview['units']->isNotEmpty())
        <section id="units" class="mh-unit-types__units" aria-labelledby="mh-unit-types-units-title">
            <h2 id="mh-unit-types-units-title" class="mh-unit-types__units-title">Onze units</h2>
            <div class="mh-unit-types__list">
                @foreach ($overview['units'] as $unit)
                    @php($image = $unit->photo3d())
                    @php($viewer = $unit->viewer())
                    <article class="mh-unit-tile">
                        @if ($viewer)
                            <div class="mh-unit-tile__media" data-unit-card>
                                <div class="mh-unit-tile__image mh-unit-tile__plan" data-unit-card-plan role="img" aria-label="Plattegrond van {{ $unit->title }}" hidden>
                                    {!! app(\App\Support\FloorPlanRenderer::class)->render($viewer['configuration']) !!}
                                </div>
                                <div class="mh-unit-tile__image mh-unit-tile__viewer" data-unit-card-viewer='@json($viewer)' role="img" aria-label="Draaibare 3D-weergave van {{ $unit->title }}">
                                    @if ($image)
                                        <img src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($image, 'medium')) }}" alt="" width="600" height="400" loading="lazy" data-unit-card-viewer-poster>
                                    @endif
                                    <span class="mh-unit-tile__viewer-hint" aria-hidden="true">Sleep om te draaien</span>
                                </div>
                                <div class="mh-unit-tile__views" role="group" aria-label="Weergave van {{ $unit->title }}">
                                    <button type="button" class="mh-unit-tile__view" data-unit-card-view="2d" aria-pressed="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>
                                        Plattegrond
                                    </button>
                                    <button type="button" class="mh-unit-tile__view is-active" data-unit-card-view="3d" aria-pressed="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                        3D
                                    </button>
                                    <button type="button" class="mh-unit-tile__view" data-unit-card-view="interior" aria-pressed="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v3"/><path d="M2 16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z"/><path d="M4 18v2"/><path d="M20 18v2"/></svg>
                                        Binnenkant
                                    </button>
                                </div>
                            </div>
                        @else
                            <a class="mh-unit-tile__image" href="{{ $unit->url() }}" tabindex="-1" aria-hidden="true">
                                @if ($image)
                                    <img src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($image, 'medium')) }}" alt="" width="600" height="400" loading="lazy">
                                @endif
                            </a>
                        @endif
                        <div class="mh-unit-tile__body">
                            <h3 class="mh-unit-tile__title"><a href="{{ $unit->url() }}">{{ $unit->title }}</a></h3>
                            @if ($unit->summary(150) !== '')
                                <p class="mh-unit-tile__text">{{ $unit->summary(150) }}</p>
                            @endif
                            <div class="mh-unit-tile__footer">
                                @if ($unit->rentPrice() || $unit->buyPrice())
                                    <dl class="mh-unit-tile__prices">
                                        @if ($unit->rentPrice())
                                            <div class="mh-unit-tile__price">
                                                <dt>Huurprijs</dt>
                                                @if (filled($unit->price_rent_week))
                                                    <dd>{{ \App\Models\Unit::euro($unit->price_rent_week) }} <small>per week</small></dd>
                                                @else
                                                    <dd>{{ $unit->rentPrice() }}</dd>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($unit->buyPrice())
                                            <div class="mh-unit-tile__price">
                                                <dt>Aanschafprijs</dt>
                                                <dd>{{ $unit->buyPrice() }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                @endif
                                <a class="mh-unit-tile__button" href="{{ $unit->url() }}">Unit bekijken <span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
