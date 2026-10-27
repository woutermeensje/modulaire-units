{{-- Overzicht van alle typen modulaire units (/modulaire-units/): hero met schuin paneel en per type een tegel met de huur- en koopopties. --}}
@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    /** @var array{intro: string, content: string, image: ?string, types: list<array{name: string, image: ?string, rent: ?\App\Models\Page, buy: ?\App\Models\Page}>} $overview */
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
                </div>
            </div>
        </div>
        <div class="mh-offer-hero__image" aria-hidden="true"></div>
    </header>

    @if ($overview['content'] !== '')
        <div class="mh-unit-types__content text-content">{!! $overview['content'] !!}</div>
    @endif

    <section class="mh-unit-types__list" aria-labelledby="mh-unit-types-list-title">
        <h2 id="mh-unit-types-list-title">Alle typen modulaire units</h2>

        <ul class="mh-unit-types__grid">
            @foreach ($overview['types'] as $type)
                <li class="mh-unit-type">
                    <a href="{{ ($type['rent'] ?? $type['buy'])->url() }}" @class(['mh-unit-type__media', 'mh-unit-type__media--no-image' => ! $type['image']]) @if ($type['image']) style="background-image: url('{{ $type['image'] }}')" @endif tabindex="-1" aria-hidden="true"></a>
                    <div class="mh-unit-type__body">
                        <h3 class="mh-unit-type__title">{{ $type['name'] }}</h3>
                        <div class="mh-unit-type__actions">
                            @if ($type['rent'])
                                <a class="mh-offer-btn mh-offer-btn--primary" href="{{ $type['rent']->url() }}" aria-label="{{ $type['name'] }} huren">Huren</a>
                            @endif
                            @if ($type['buy'])
                                <a class="mh-offer-btn mh-offer-btn--outline" href="{{ $type['buy']->url() }}" aria-label="{{ $type['name'] }} kopen">Kopen</a>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</main>
@endsection
