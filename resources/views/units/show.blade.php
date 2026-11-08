@extends('layouts.site')

@section('body_class', 'single single-mh_unit')

@section('content')
    <main class="mhu-single-page" id="content">
        <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ url('/') }}/">Home</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="{{ route('units.index') }}/">Units</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $unit->title }}</span>
        </nav>

        <div class="mh-product-wrapper">
            @if ($viewer)
                <div class="mh-col-left">
                    @include('partials.unit-3d-view', ['unit' => $unit, 'viewer' => $viewer, 'poster' => $unit->photo3d(), 'posterVariant' => 'large'])
                </div>
            @else
                @include('partials.item-gallery', ['images' => $unit->images(), 'title' => $unit->title])
            @endif

            <div class="mh-col-right">
                {{-- Opens the composer with this unit's configuration as the starting point. --}}
                @php($composeUrl = route('unit-composer.public').'/'.($viewer ? '?'.http_build_query([\App\Support\UnitConfiguration::SHARE_KEY => \App\Support\UnitConfiguration::toShareCode($viewer['configuration'])]) : ''))
                <div class="mh-col-right__head">
                    <h1 class="product_title entry-title">{{ $unit->title }}</h1>
                    <a class="mh-compose-link" href="{{ $composeUrl }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        Stel je eigen unit samen
                    </a>
                </div>

                @if ($unit->terms->isNotEmpty())
                    <div class="mh-product-cats">
                        @foreach ([\App\Models\UnitTerm::OFFER, \App\Models\UnitTerm::TYPE, \App\Models\UnitTerm::CONDITION] as $taxonomy)
                            @foreach ($unit->termsFor($taxonomy) as $term)
                                <a href="{{ $term->url() }}">{{ $term->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                @endif

                @include('partials.quote-cta', ['type' => 'unit', 'id' => $unit->id])

                @if ($unit->excerpt)
                    <div class="mh-intro-text"><p>{{ $unit->summary(80) }}</p></div>
                @endif

                @if ($unit->rentPrice() || $unit->buyPrice())
                    <div class="mh-price-block">
                        <h3 class="mh-price-block__heading">Prijsindicatie</h3>
                        <div class="mh-price-block__cards">
                            @if ($unit->rentPrice())
                                <div class="mh-price-card">
                                    <div class="mh-price-card__header">
                                        <span class="mh-price-card__label">Huren</span>
                                    </div>
                                    <span class="mh-price-card__value">{{ $unit->rentPrice() }}</span>
                                </div>
                            @endif
                            @if ($unit->buyPrice())
                                <div class="mh-price-card">
                                    <div class="mh-price-card__header">
                                        <span class="mh-price-card__label">Kopen</span>
                                    </div>
                                    <span class="mh-price-card__value">{{ $unit->buyPrice() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if (trim(strip_tags((string) $unit->content)) !== '')
            <div class="mh-col-description">
                <h2>Unit beschrijving</h2>
                <div class="text-content">{!! $content !!}</div>
            </div>
        @endif

        @if ($relatedUnits->isNotEmpty())
            <div class="mh-col-description mh-related">
                <h2>Gerelateerde units</h2>
                <div class="mh-related-grid">
                    @foreach ($relatedUnits as $related)
                        <a href="{{ $related->url() }}" class="mh-related-item">
                            <div class="mh-related-thumb">
                                @if ($related->featured_image)
                                    <img width="300" height="200" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($related->featured_image, 'medium')) }}" alt="{{ $related->featured_image_alt ?: $related->title }}" loading="lazy">
                                @endif
                            </div>
                            <span class="mh-related-name">{{ $related->title }}</span>
                            @php($relatedTerms = $related->termsFor(\App\Models\UnitTerm::TYPE)->take(1)->merge($related->termsFor(\App\Models\UnitTerm::CONDITION)->take(1)))
                            @if ($relatedTerms->isNotEmpty())
                                <span class="mh-related-cats">{{ $relatedTerms->pluck('name')->implode(' • ') }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
@endsection
