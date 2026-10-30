@extends('layouts.site')

@section('body_class', 'page unit-composer-page')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    @vite(['resources/css/unit-composer.css', 'resources/js/unit-composer.js'])
@endpush

@section('content')
    <main id="content" class="site-main page type-page unit-composer">
        <h1 class="mh-page-title">Unit samenstellen</h1>
        <p class="unit-composer__intro">Stel je eigen modulaire unit samen: kies de afmeting, het aantal units en lagen, de kleuren en waar ramen en deuren komen. Bewaar of deel je samenstelling met de link.</p>

        @include('admin.fields.configurator', [
            'field' => \App\Admin\Field::make('configuration', 'Jouw samenstelling', 'configurator'),
            'record' => null,
            'value' => $configuration,
            'composer' => true,
            'ai' => false,
            'previewUrl' => route('unit-composer.public.preview'),
            'quoteUrl' => route('unit-composer.quote'),
            'quoteReady' => $quoteReady,
        ])
    </main>
@endsection
