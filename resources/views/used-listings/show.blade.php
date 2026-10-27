@extends('layouts.site')

@php
    /** @var \App\Models\UsedListing $listing */
@endphp

@section('body_class', 'single mh-used-single')

@section('content')
    <main class="mhu-single-page" id="content">
        <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ url('/') }}/">Home</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="/{{ \App\Http\Controllers\UsedListingController::PAGE_PATH }}/">Gebruikt</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $listing->title }}</span>
        </nav>

        <div class="mh-product-wrapper">
            @include('partials.item-gallery', ['images' => $listing->gallery(), 'title' => $listing->title])

            <div class="mh-col-right">
                <h1 class="product_title entry-title">{{ $listing->title }}</h1>

                <div class="mh-product-cats">
                    <span>Gebruikt</span>
                    <span>📍 {{ $listing->location }}</span>
                </div>

                <div class="mh-price-block">
                    <h3 class="mh-price-block__heading">Vraagprijs</h3>
                    <div class="mh-price-block__cards">
                        <div class="mh-price-card">
                            <div class="mh-price-card__header">
                                <svg class="mh-price-card__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                <span class="mh-price-card__label">Kopen</span>
                            </div>
                            <span class="mh-price-card__value">{{ $listing->formattedPrice() }}</span>
                        </div>
                    </div>
                </div>

                <a class="mh-offer-btn mh-used__respond-link" href="#reageren">Reageren op deze unit</a>
            </div>
        </div>

        <div class="mh-col-description">
            <h2>Beschrijving</h2>
            <div class="mh-used__description text-content">{!! nl2br(e($listing->description)) !!}</div>
        </div>

        <section class="mh-col-description mh-used__respond" id="reageren" aria-labelledby="mh-used-respond-title">
            <h2 id="mh-used-respond-title">Interesse in deze unit?</h2>

            @if (session('form_success'))
                <div class="mh-form-notice mh-form-notice--success" role="status">{{ session('form_success') }}</div>
            @else
                <form class="mh-form mh-used__respond-form" method="post" action="{{ route('used-listings.respond', $listing->slug) }}">
                    @csrf
                    <div class="mh-form__honeypot" aria-hidden="true">
                        <label for="respond-website">Website</label>
                        <input type="text" id="respond-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="mh-form__grid mh-form__grid--2">
                        <div class="mh-form__field">
                            <label class="mh-form__label" for="respond-name">Naam <span class="mh-form__req">*</span></label>
                            <input class="mh-form__input" type="text" id="respond-name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                            @error('name')<span class="mh-form__error">{{ $message }}</span>@enderror
                        </div>
                        <div class="mh-form__field">
                            <label class="mh-form__label" for="respond-email">E-mailadres <span class="mh-form__req">*</span></label>
                            <input class="mh-form__input" type="email" id="respond-email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                            @error('email')<span class="mh-form__error">{{ $message }}</span>@enderror
                        </div>
                        <div class="mh-form__field">
                            <label class="mh-form__label" for="respond-phone">Telefoonnummer <span class="mh-form__opt">(optioneel)</span></label>
                            <input class="mh-form__input" type="tel" id="respond-phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                            @error('phone')<span class="mh-form__error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="mh-form__grid mh-form__grid--1">
                        <div class="mh-form__field">
                            <label class="mh-form__label" for="respond-message">Bericht <span class="mh-form__req">*</span></label>
                            <textarea class="mh-form__input" id="respond-message" name="message" rows="5" required>{{ old('message') }}</textarea>
                            @error('message')<span class="mh-form__error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="mh-form__footer">
                        <p class="mh-form__footer-note">We brengen je in contact met de aanbieder.</p>
                        <button type="submit" class="mh-form__submit">Versturen</button>
                    </div>
                </form>
            @endif
        </section>
    </main>
@endsection
