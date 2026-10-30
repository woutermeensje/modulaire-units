@extends('layouts.site')

@php
    /** @var \App\Models\Page $page */
    $isElementor = str_contains((string) $page->content, 'elementor');
@endphp

@section('body_class', trim('page page-id-'.$page->id.' page-'.($page->slug ?: 'home').($isElementor ? ' elementor-page elementor-page-'.($page->wp_id ?? $page->id) : '')))

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
        <div class="page-content text-content">
            {!! $content !!}
        </div>
    </main>
@endsection
