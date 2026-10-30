@extends('layouts.site', ['seo' => new \App\Support\Seo(title: 'Pagina niet gevonden - '.\App\Models\Setting::get('site_name'), noindex: true)])

@section('content')
    <main id="content" class="site-main mh-not-found">
        <div class="mh-qp__empty">
            <h1>Pagina niet gevonden</h1>
            <p>De pagina die je zoekt bestaat niet (meer). Bekijk ons aanbod of neem contact met ons op.</p>
            <p>
                <a class="mh-btn mh-btn--accent" href="{{ url('modulaire-units') }}/">Bekijk aanbod</a>
                <a class="mh-btn mh-btn--outline" href="{{ url('contact') }}/">Contact</a>
            </p>
        </div>
    </main>
@endsection
