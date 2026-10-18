@extends('layouts.portal')

@section('title', 'Dashboard')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => 'Dashboard', 'crumbs' => ['Dashboard' => null]])

        {{-- De definitieve inhoud van het klantdashboard volgt nog; dit zijn de vaste startkaarten. --}}
        <section class="sales-card-grid">
            <article class="card sales-card">
                <div class="sales-card__icon"><i class="ph ph-shopping-bag"></i></div>
                <p class="sales-card__meta">Offerte</p>
                <h2 class="sales-card__title">Mijn offerte</h2>
                <p class="sales-card__text">
                    @if ($quoteCount > 0)
                        Je hebt {{ $quoteCount }} {{ $quoteCount === 1 ? 'item' : 'items' }} in je offerte staan. Rond je aanvraag af.
                    @else
                        Stel je offerte samen door units aan je offerte toe te voegen.
                    @endif
                </p>
                <a class="sales-card__link" href="{{ route('quote.show') }}/">
                    <span>Bekijk offerte</span>
                    <i class="ph ph-arrow-right"></i>
                </a>
            </article>

            <article class="card sales-card">
                <div class="sales-card__icon"><i class="ph ph-cube"></i></div>
                <p class="sales-card__meta">Kopen, huren & gebruikt</p>
                <h2 class="sales-card__title">Aanbod bekijken</h2>
                <p class="sales-card__text">Bekijk alle modulaire units: kantoorunits, woonunits, sanitaire units, containers en meer.</p>
                <a class="sales-card__link" href="{{ url('aanbod') }}/">
                    <span>Naar het aanbod</span>
                    <i class="ph ph-arrow-right"></i>
                </a>
            </article>

            <article class="card sales-card">
                <div class="sales-card__icon"><i class="ph ph-chat-centered-text"></i></div>
                <p class="sales-card__meta">Advies & vragen</p>
                <h2 class="sales-card__title">Contact opnemen</h2>
                <p class="sales-card__text">Laat een van onze adviseurs je helpen bij het kiezen van de juiste modulaire huisvesting.</p>
                <a class="sales-card__link" href="{{ url('offerte-aanvragen') }}/">
                    <span>Informatie aanvragen</span>
                    <i class="ph ph-arrow-right"></i>
                </a>
            </article>
        </section>
    </div>
</section>
@endsection
