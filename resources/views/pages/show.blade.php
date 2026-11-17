@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    $isElementor = str_contains((string) $page->content, 'elementor');
@endphp

@section('body_class', trim('page page-id-'.$page->id.' page-'.($page->source('slug') ?: 'home').($isElementor ? ' elementor-page elementor-page-'.($page->wp_id ?? $page->id) : '')))

@section('custom_css'){!! $page->custom_css !!}@endsection

@section('content')
    @if ($page->template === 'landing' && ! empty($page->hero))
        @include('partials.hero', ['hero' => $page->hero])
    @endif

    @isset($composer)
        @include('partials.unit-composer-section', ['composer' => $composer])
    @endisset

    <main id="content" class="site-main page type-page">
        @if ($page->show_title)
            <div class="page-header">
                <h1 class="entry-title">{{ $page->title }}</h1>
            </div>
        @endif
        @if (! empty($blocks))
            {{-- Homepage: de tekst opgeknipt in losse blokken, per h2 een kaart (App\Services\Content\ContentBlocks). --}}
            <section class="mh-home-blocks" @if ($blocks['title']) aria-labelledby="mh-home-blocks-title" @endif>
                @if ($blocks['title'] || $blocks['intro'] !== '')
                    <header class="mh-home-blocks__header">
                        @if ($blocks['title'])
                            <h1 id="mh-home-blocks-title" class="mh-home-blocks__title">{{ $blocks['title'] }}</h1>
                        @endif
                        @if ($blocks['intro'] !== '')
                            <div class="mh-home-blocks__intro text-content">{!! $blocks['intro'] !!}</div>
                        @endif
                    </header>
                @endif
                <div class="mh-home-blocks__grid">
                    @foreach ($blocks['blocks'] as $block)
                        <article class="mh-home-block">
                            <h2 class="mh-home-block__title">{{ $block['heading'] }}</h2>
                            <div class="mh-home-block__body text-content">{!! $block['body'] !!}</div>
                            @if ($block['button'])
                                <a class="mh-btn mh-btn--outline mh-home-block__button" href="{{ $block['button']['url'] }}">{{ $block['button']['label'] }}</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @else
            <div class="page-content text-content">
                {!! $content !!}
            </div>
        @endif
    </main>
@endsection
