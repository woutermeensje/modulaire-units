{{-- Gebruikte units die bezoekers zelf aanbieden (/gebruikt/): kop met "Units aanbieden", de advertenties en de paginatekst. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var \Illuminate\Support\Collection<int, \App\Models\UsedListing> $listings */
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->source('slug').' mh-used-page')

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
<main id="content" class="mh-offer mh-used" aria-labelledby="mh-used-title">
    <header class="mh-used__header">
        <div>
            <h1 id="mh-used-title" class="text-title">{{ $page->title }}</h1>
            <p class="text-lead">{{ $page->hero['subtitle'] ?? __('Gebruikte modulaire units, aangeboden door bedrijven en organisaties. Bekijk het aanbod of bied zelf gratis units aan.') }}</p>
        </div>
        <a class="mh-offer-btn mh-offer-btn--primary mh-used__cta" href="{{ \App\Support\SiteUrl::route('used-listings.create') }}">{{ __('Units aanbieden') }}</a>
    </header>

    @if ($listings->isEmpty())
        <p class="mh-used__empty">{{ __('Er worden op dit moment geen gebruikte units aangeboden. Heb je zelf een unit over?') }} <a href="{{ \App\Support\SiteUrl::route('used-listings.create') }}">{{ __('Bied hem gratis aan.') }}</a></p>
    @else
        <div class="mh-unit-types__list mh-used__list">
            @foreach ($listings as $listing)
                @php($photoCount = count($listing->gallery()))
                <article class="mh-unit-tile">
                    <a class="mh-unit-tile__image" href="{{ $listing->url() }}" tabindex="-1" aria-hidden="true">
                        @if ($listing->coverImage())
                            <img src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($listing->coverImage(), 'medium_large')) }}" alt="" width="600" height="400" loading="lazy">
                        @endif
                        @if ($photoCount > 1)
                            <span class="mh-unit-tile__count">{{ __(':count foto\'s', ['count' => $photoCount]) }}</span>
                        @endif
                    </a>
                    <div class="mh-unit-tile__body">
                        <h2 class="mh-unit-tile__title"><a href="{{ $listing->url() }}">{{ $listing->title }}</a></h2>
                        @if ($listing->summary(40) !== '')
                            <p class="mh-unit-tile__text">{{ $listing->summary(40) }}</p>
                        @endif
                        <div class="mh-unit-tile__footer">
                            <dl class="mh-unit-tile__prices">
                                <div class="mh-unit-tile__price">
                                    <dt>{{ __('Aanschafprijs') }}</dt>
                                    <dd>{{ $listing->formattedPrice() }}</dd>
                                </div>
                                @if (filled($listing->location))
                                    <div class="mh-unit-tile__price">
                                        <dt>{{ __('Locatie') }}</dt>
                                        <dd>{{ $listing->location }}</dd>
                                    </div>
                                @endif
                            </dl>
                            <a class="mh-unit-tile__button" href="{{ $listing->url() }}">{{ __('Unit bekijken') }} <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($content !== '')
        <div class="mh-used__content text-content">{!! $content !!}</div>
    @endif
</main>
@endsection
