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
        <section class="unit-composer unit-composer--home" id="unit-samensteller">
            <h2 class="mh-page-title">Stel je eigen unit samen</h2>
            <p class="unit-composer__intro">Kies de afmeting, het aantal units en lagen, de kleuren en waar ramen en deuren komen. Bewaar of deel je samenstelling met de link.</p>

            @include('partials.unit-composer', $composer)
        </section>
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
