@extends('layouts.site', ['seo' => new \App\Support\Seo(title: __('Pagina niet gevonden').' - '.\App\Models\Setting::get('site_name'), noindex: true)])

@section('content')
    <main id="content" class="site-main mh-not-found">
        <div class="mh-qp__empty">
            <h1>{{ __('Pagina niet gevonden') }}</h1>
            <p>{{ __('De pagina die je zoekt bestaat niet (meer). Bekijk ons aanbod of neem contact met ons op.') }}</p>
            <p>
                <a class="mh-btn mh-btn--accent" href="{{ url('modulaire-units') }}/">{{ __('Bekijk aanbod') }}</a>
                <a class="mh-btn mh-btn--outline" href="{{ url('contact') }}/">{{ __('Contact') }}</a>
            </p>
        </div>
    </main>
@endsection
