{{-- Overzichtspagina van de modulaire units (/modulaire-units/): de pagina-inhoud en de gepubliceerde units die in het account zijn samengesteld. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{content: string, units: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Unit>} $overview */
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->source('slug').' mh-unit-types-page')

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
<main id="content" class="mh-offer mh-unit-types" aria-labelledby="mh-unit-types-title">
    <h1 id="mh-unit-types-title" class="screen-reader-text">{{ $page->title }}</h1>

    @if ($overview['content'] !== '')
        <div class="mh-unit-types__content text-content">{!! $overview['content'] !!}</div>
    @endif

    @if ($overview['units']->isNotEmpty())
        <section id="units" class="mh-unit-types__units" aria-labelledby="mh-unit-types-units-title">
            <h2 id="mh-unit-types-units-title" class="mh-unit-types__units-title">{{ __('Onze units') }}</h2>
            <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ \App\Support\SiteUrl::path('') }}">{{ __('Home') }}</a>
                <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
                <span aria-current="page">{{ $page->title }}</span>
            </nav>
            <div class="mh-unit-types__list">
                @foreach ($overview['units'] as $unit)
                    @php($image = $unit->photo3d())
                    @php($viewer = $unit->viewer())
                    <article class="mh-unit-tile">
                        @if ($viewer)
                            @include('partials.unit-3d-view', ['unit' => $unit, 'viewer' => $viewer, 'poster' => $image])
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
                                                <dt>{{ __('Huurprijs') }}</dt>
                                                @if (filled($unit->price_rent_week))
                                                    <dd>{{ \App\Models\Unit::euro($unit->price_rent_week) }} <small>{{ __('per week') }}</small></dd>
                                                @else
                                                    <dd>{{ $unit->rentPrice() }}</dd>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($unit->buyPrice())
                                            <div class="mh-unit-tile__price">
                                                <dt>{{ __('Aanschafprijs') }}</dt>
                                                <dd>{{ $unit->buyPrice() }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                @endif
                                <a class="mh-unit-tile__button" href="{{ $unit->url() }}">{{ __('Unit bekijken') }} <span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
