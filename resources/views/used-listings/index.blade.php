{{-- Gebruikte units die bezoekers zelf aanbieden (/gebruikt/): kop met "Units aanbieden", de advertenties en de paginatekst. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var \Illuminate\Support\Collection<int, \App\Models\UsedListing> $listings */
@endphp

@section('body_class', 'page page-id-'.$page->id.' page-'.$page->slug.' mh-used-page')

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
<main id="content" class="mh-offer mh-used" aria-labelledby="mh-used-title">
    <header class="mh-used__header">
        <div>
            <h1 id="mh-used-title" class="text-title">{{ $page->title }}</h1>
            <p class="text-lead">{{ $page->hero['subtitle'] ?? 'Gebruikte modulaire units, aangeboden door bedrijven en organisaties. Bekijk het aanbod of bied zelf gratis units aan.' }}</p>
        </div>
        <a class="mh-offer-btn mh-offer-btn--primary mh-used__cta" href="{{ \App\Support\SiteUrl::path(route('used-listings.create', [], false)) }}">Units aanbieden</a>
    </header>

    @if ($listings->isEmpty())
        <p class="mh-used__empty">Er worden op dit moment geen gebruikte units aangeboden. Heb je zelf een unit over? <a href="{{ \App\Support\SiteUrl::path(route('used-listings.create', [], false)) }}">Bied hem gratis aan.</a></p>
    @else
        <ul class="mh-used__grid">
            @foreach ($listings as $listing)
                <li class="mh-used-card">
                    <a class="mh-used-card__link" href="{{ $listing->url() }}">
                        <div class="mh-used-card__media">
                            @if ($listing->coverImage())
                                <img src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($listing->coverImage(), 'medium_large')) }}" alt="{{ $listing->title }}" loading="lazy">
                            @endif
                            @if (count($listing->gallery()) > 1)
                                <span class="mh-used-card__count">{{ count($listing->gallery()) }} foto's</span>
                            @endif
                        </div>
                        <div class="mh-used-card__body">
                            <h2 class="mh-used-card__title">{{ $listing->title }}</h2>
                            <p class="mh-used-card__location">{{ $listing->location }}</p>
                            <p class="mh-used-card__price">{{ $listing->formattedPrice() }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($content !== '')
        <div class="mh-used__content text-content">{!! $content !!}</div>
    @endif
</main>
@endsection
