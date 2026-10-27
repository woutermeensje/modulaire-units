{{-- Formulier waarmee bezoekers gratis een gebruikte unit aanbieden (/units-aanbieden/). --}}
@extends('layouts.site')

@section('body_class', 'page mh-used-page mh-used-create-page')

@section('content')
<main id="content" class="mh-offer mh-used" aria-labelledby="mh-used-create-title">
    <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ url('/') }}/">Home</a>
        <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
        <a href="/{{ \App\Http\Controllers\UsedListingController::PAGE_PATH }}/">Gebruikt</a>
        <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
        <span aria-current="page">Units aanbieden</span>
    </nav>

    <div class="mh-used__form-wrap">
        @if (session('form_success'))
            <div class="mh-form-notice mh-form-notice--success" role="status">{{ session('form_success') }}</div>
        @else
            <form class="mh-form" method="post" action="{{ route('used-listings.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mh-form__honeypot" aria-hidden="true">
                    <label for="used-website">Website</label>
                    <input type="text" id="used-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="mh-form__header">
                    <h1 id="mh-used-create-title" class="mh-form__title">Units aanbieden</h1>
                    <p class="mh-form__subtitle">Heb je gebruikte modulaire units die je wilt verkopen? Plaats ze hier <strong>gratis</strong>. Na een korte controle verschijnt je advertentie op de pagina Gebruikt.</p>
                </div>

                <div class="mh-form__block">
                    <div class="mh-form__section">
                        <h2 class="mh-form__section-title">Over de unit</h2>
                        <div class="mh-form__grid mh-form__grid--1">
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-title">Titel <span class="mh-form__req">*</span></label>
                                <input class="mh-form__input" type="text" id="used-title" name="title" value="{{ old('title') }}" maxlength="150" placeholder="Bijv. 4 gekoppelde kantoorunits 6 x 3 m" required>
                                @error('title')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-description">Beschrijving <span class="mh-form__req">*</span></label>
                                <textarea class="mh-form__input" id="used-description" name="description" rows="7" maxlength="5000" placeholder="Afmetingen, bouwjaar, staat, voorzieningen, beschikbaarheid…" required>{{ old('description') }}</textarea>
                                @error('description')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="mh-form__grid mh-form__grid--2">
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-location">Locatie <span class="mh-form__req">*</span></label>
                                <input class="mh-form__input" type="text" id="used-location" name="location" value="{{ old('location') }}" maxlength="150" placeholder="Plaats of regio" required>
                                @error('location')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-price">Prijs in euro's <span class="mh-form__req">*</span></label>
                                <input class="mh-form__input" type="number" id="used-price" name="price" value="{{ old('price') }}" min="1" step="1" inputmode="numeric" placeholder="Bijv. 12500" required>
                                @error('price')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="mh-form__section">
                        <h2 class="mh-form__section-title">Afbeeldingen</h2>
                        <div class="mh-form__field">
                            <label class="mh-form__label" for="used-images">Foto's <span class="mh-form__req">*</span> <span class="mh-form__opt">maximaal {{ \App\Models\UsedListing::MAX_IMAGES }}, jpg/png/webp tot 10 MB per foto</span></label>
                            <input class="mh-form__input mh-used__file" type="file" id="used-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required data-max-files="{{ \App\Models\UsedListing::MAX_IMAGES }}">
                            <p class="mh-form__hint">De eerste foto wordt de hoofdfoto van je advertentie.</p>
                            @error('images')<span class="mh-form__error">{{ $message }}</span>@enderror
                            @foreach ($errors->get('images.*') as $messages)
                                <span class="mh-form__error">{{ $messages[0] }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="mh-form__section">
                        <h2 class="mh-form__section-title">Jouw gegevens</h2>
                        <p class="mh-form__hint">Deze gegevens worden niet op de website getoond. Reacties van geïnteresseerden sturen we naar jou door.</p>
                        <div class="mh-form__grid mh-form__grid--1">
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-name">Naam <span class="mh-form__req">*</span></label>
                                <input class="mh-form__input" type="text" id="used-name" name="contact_name" value="{{ old('contact_name') }}" autocomplete="name" required>
                                @error('contact_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="mh-form__grid mh-form__grid--2">
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-email">E-mailadres <span class="mh-form__req">*</span></label>
                                <input class="mh-form__input" type="email" id="used-email" name="contact_email" value="{{ old('contact_email') }}" autocomplete="email" required>
                                @error('contact_email')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                            <div class="mh-form__field">
                                <label class="mh-form__label" for="used-phone">Telefoonnummer <span class="mh-form__opt">(optioneel)</span></label>
                                <input class="mh-form__input" type="tel" id="used-phone" name="contact_phone" value="{{ old('contact_phone') }}" autocomplete="tel">
                                @error('contact_phone')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mh-form__footer">
                    <p class="mh-form__footer-note">Het aanbieden van units is gratis.</p>
                    <button type="submit" class="mh-form__submit">Gratis aanbieden</button>
                </div>
            </form>
        @endif
    </div>
</main>
@endsection
