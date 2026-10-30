@extends('layouts.site')

@section('body_class', 'page mh-quote-page')

@section('content')
    <main id="content" class="site-main page type-page">
        <div class="page-header">
            <h1 class="entry-title">Mijn offerte</h1>
        </div>
        <div class="page-content">
            <div class="mh-qp">
                @if (session('quote_success'))
                    <div class="mh-qp__card mh-qp__card--success" role="status">
                        <div class="mh-qp__empty">
                            <div class="mh-qp__empty-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <h3>Bedankt voor je aanvraag!</h3>
                            <p>{{ session('quote_success') }}</p>
                            <a href="{{ url('aanbod') }}/">Bekijk onze units</a>
                        </div>
                    </div>
                @elseif ($items->isEmpty())
                    <div class="mh-qp__empty">
                        <div class="mh-qp__empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10h14"/><path d="M6 10l1.25 8.5A2 2 0 0 0 9.23 20h5.54a2 2 0 0 0 1.98-1.5L18 10"/><path d="M9 10V8a3 3 0 0 1 6 0v2"/></svg>
                        </div>
                        <h3>Je offerte is leeg</h3>
                        <p>Voeg units toe via de unitpagina's.</p>
                        <a href="{{ url('aanbod') }}/">Bekijk onze units</a>
                    </div>
                @else
                    <form method="post" action="{{ route('quote.update') }}">
                        @csrf
                        <div class="mh-qp__card">
                            <div class="mh-qp__card-header">
                                <h2 class="mh-qp__card-title">Geselecteerde items</h2>
                                <span class="mh-qp__item-count">{{ $items->count() }}</span>
                            </div>
                            <div class="mh-qp__card-body">
                                @foreach ($items as $item)
                                    @php
                                        $model = $item['model'];
                                        $isComposition = $model instanceof \App\Support\ComposedUnit;
                                        $title = $isComposition ? $model->title() : $model->title;
                                        $thumb = $isComposition ? $model->image() : \App\Support\SiteUrl::media(\App\Models\Media::variant($model->featured_image, 'thumbnail'));                                        $categories = match (true) {
                                            $isComposition => $model->summary(),
                                            $item['type'] === 'unit' => collect([$model->offerLabel(), $model->termsFor(\App\Models\UnitTerm::CONDITION)->first()?->name])->filter()->implode(' • '),
                                            default => 'Product',
                                        };
                                    @endphp
                                    <div class="mh-qp__product-row">
                                        @if ($thumb)
                                            <img width="64" height="64" src="{{ $thumb }}" class="mh-qp__thumb{{ $isComposition ? ' mh-qp__thumb--composition' : '' }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <span class="mh-qp__thumb-placeholder" aria-hidden="true"></span>
                                        @endif
                                        <div class="mh-qp__product-info">
                                            <a href="{{ $model->url() }}" class="mh-qp__product-name">{{ $title }}</a>
                                            <span class="mh-qp__product-cat">{{ $categories }}</span>
                                        </div>
                                        <div class="mh-qp__product-actions">
                                            <div class="mh-qp__stepper" data-stepper>
                                                <button type="button" class="mh-qp__qty-btn mh-qp__qty-btn--minus" aria-label="Minder">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                </button>
                                                <input type="number" class="mh-qp__qty-input" name="quantities[{{ $item['key'] }}]" value="{{ $item['quantity'] }}" min="1" aria-label="Aantal">
                                                <button type="button" class="mh-qp__qty-btn mh-qp__qty-btn--plus" aria-label="Meer">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                </button>
                                            </div>
                                            <button type="submit" class="mh-qp__remove-btn" name="remove" value="{{ $item['key'] }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                Verwijderen
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mh-qp__card-footer">
                                <div class="mh-qp__footer-left">
                                    <button type="submit" class="mh-qp__btn mh-qp__btn--outline">Aantallen bijwerken</button>
                                    <a href="{{ url('aanbod') }}/" class="mh-qp__btn mh-qp__btn--outline">+ Meer units toevoegen</a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <form class="mh-form mh-qp__form" method="post" action="{{ route('quote.submit') }}">
                        @csrf
                        <div class="mh-form__honeypot" aria-hidden="true">
                            <label for="mh_website">Website</label>
                            <input type="text" id="mh_website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="mh-qp__card">
                            <div class="mh-qp__card-header">
                                <h2 class="mh-qp__card-title">Jouw gegevens</h2>
                            </div>
                            <div class="mh-qp__card-body mh-qp__card-body--form">
                                <div class="mh-form__grid mh-form__grid--2">
                                    <div class="mh-form__field">
                                        <label class="mh-form__label" for="mh_firstname">Voornaam <span class="mh-form__req">*</span></label>
                                        <input class="mh-form__input" type="text" id="mh_firstname" name="first_name" value="{{ old('first_name') }}" required placeholder="Voornaam" autocomplete="given-name">
                                        @error('first_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="mh-form__field">
                                        <label class="mh-form__label" for="mh_lastname">Achternaam <span class="mh-form__req">*</span></label>
                                        <input class="mh-form__input" type="text" id="mh_lastname" name="last_name" value="{{ old('last_name') }}" required placeholder="Achternaam" autocomplete="family-name">
                                        @error('last_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="mh-form__field">
                                        <label class="mh-form__label" for="mh_email">E-mailadres <span class="mh-form__req">*</span></label>
                                        <input class="mh-form__input" type="email" id="mh_email" name="email" value="{{ old('email') }}" required placeholder="naam@bedrijf.nl" autocomplete="email">
                                        @error('email')<span class="mh-form__error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="mh-form__field">
                                        <label class="mh-form__label" for="mh_phone">Telefoonnummer <span class="mh-form__opt">(optioneel)</span></label>
                                        <input class="mh-form__input" type="tel" id="mh_phone" name="phone" value="{{ old('phone') }}" placeholder="06 – 12 34 56 78" autocomplete="tel">
                                        @error('phone')<span class="mh-form__error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="mh-form__field mh-form__field--full">
                                        <label class="mh-form__label" for="mh_message">Opmerkingen of vragen <span class="mh-form__opt">(optioneel)</span></label>
                                        <textarea class="mh-form__input" id="mh_message" name="message" rows="5" placeholder="Bijv. leveringsadres, gewenste datum of specifieke wensen...">{{ old('message') }}</textarea>
                                        @error('message')<span class="mh-form__error">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="mh-form__footer">
                                    <button type="submit" class="mh-form__submit">Offerte aanvragen</button>
                                    <p class="mh-form__footer-note">Je gegevens worden uitsluitend gebruikt voor het verwerken van je offerte aanvraag.</p>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </main>
@endsection
