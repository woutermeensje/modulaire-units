{{-- Overzichtspagina van de modulaire units (/modulaire-units/): hero met schuin paneel, de pagina-inhoud en de unit samensteller. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{intro: string, content: string, image: ?string} $overview */
    /** @var array{configuration: array<string, mixed>} $composer */
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
                    <a class="mh-offer-btn mh-offer-btn--dark" href="#unit-samensteller">Units samenstellen</a>
                </div>
            </div>
        </div>
        <div class="mh-offer-hero__image" aria-hidden="true"></div>
    </header>

    @if ($overview['content'] !== '')
        <div class="mh-unit-types__content text-content">{!! $overview['content'] !!}</div>
    @endif

    @include('partials.unit-composer-section', ['composer' => $composer])
</main>
@endsection
