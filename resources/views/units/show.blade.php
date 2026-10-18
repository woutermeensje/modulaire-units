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
            @include('partials.item-gallery', ['images' => $unit->images(), 'title' => $unit->title])

            <div class="mh-col-right">
                <h1 class="product_title entry-title">{{ $unit->title }}</h1>

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

                @if ($unit->price_rent || $unit->price_buy)
                    <div class="mh-price-block">
                        <h3 class="mh-price-block__heading">Prijsindicatie</h3>
                        <div class="mh-price-block__cards">
                            @if ($unit->price_rent)
                                <div class="mh-price-card">
                                    <div class="mh-price-card__header">
                                        <svg class="mh-price-card__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                        <span class="mh-price-card__label">Huren</span>
                                    </div>
                                    <span class="mh-price-card__value">{{ $unit->price_rent }}</span>
                                </div>
                            @endif
                            @if ($unit->price_buy)
                                <div class="mh-price-card">
                                    <div class="mh-price-card__header">
                                        <svg class="mh-price-card__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                        <span class="mh-price-card__label">Kopen</span>
                                    </div>
                                    <span class="mh-price-card__value">{{ $unit->price_buy }}</span>
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
                {!! $content !!}
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
