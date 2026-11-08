@extends('layouts.site')

@section('body_class', 'search')

@section('content')
    <main class="mhu-single-page" id="content">
        <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ url('/') }}/">{{ __('Home') }}</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ __('Zoeken') }}</span>
        </nav>
        <div class="mh-search">
            <h1 class="mh-units-catalog__title">{{ $query !== '' ? __('Zoekresultaten voor “:query”', ['query' => $query]) : __('Zoekresultaten') }}</h1>
            <form class="mh-search__form" method="get" action="{{ \App\Support\SiteUrl::route('search') }}">
                <label class="screen-reader-text" for="mh-search-page-input">{{ __('Zoeken') }}</label>
                <input id="mh-search-page-input" type="search" name="s" value="{{ $query }}" placeholder="{{ __('Zoek units, pagina\'s of artikelen') }}">
                <button type="submit" class="mh-btn mh-btn--accent">{{ __('Zoeken') }}</button>
            </form>

            @if ($query === '')
                <p>{{ __('Vul een zoekterm in om te zoeken.') }}</p>
            @else
                <h2 class="mh-blog__list-title">{{ __('Units (:count)', ['count' => $units->count()]) }}</h2>
                @include('shortcodes.units-grid', ['units' => $units])

                @if ($otherResults->isNotEmpty())
                    <h2 class="mh-blog__list-title">{{ __('Pagina\'s en artikelen (:count)', ['count' => $otherResults->count()]) }}</h2>
                    <ul class="mh-search__results">
                        @foreach ($otherResults as $result)
                            <li><a href="{{ $result['url'] }}">{{ $result['title'] }}</a>@if ($result['excerpt'])<p>{{ $result['excerpt'] }}</p>@endif</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </main>
@endsection
