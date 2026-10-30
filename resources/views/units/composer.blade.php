@extends('layouts.site')

@section('body_class', 'page unit-composer-page')

@section('content')
    <main id="content" class="site-main page type-page unit-composer">
        <h1 class="mh-page-title">Unit samenstellen</h1>
        <p class="unit-composer__intro">Stel je eigen modulaire unit samen: kies de afmeting, het aantal units en lagen, de kleuren en waar ramen en deuren komen. Bewaar of deel je samenstelling met de link.</p>

        @include('partials.unit-composer', ['configuration' => $configuration, 'quoteReady' => $quoteReady])
    </main>
@endsection
