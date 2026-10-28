{{-- Formulier waarmee een organisatie vanuit het account gratis een gebruikte unit aanbiedt. --}}
@extends('layouts.portal')

@section('title', 'Units aanbieden')

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => 'Units aanbieden',
            'crumbs' => ['Dashboard' => route('dashboard'), 'Mijn units' => route('my-used-listings.index'), 'Aanbieden' => null],
            'subtitle' => 'Heb je gebruikte modulaire units die je wilt verkopen? Plaats ze hier gratis. Na een korte controle verschijnt je advertentie op de pagina Gebruikt.',
        ])

        <form method="POST" action="{{ route('my-used-listings.store') }}" enctype="multipart/form-data" class="portal-form">
            @csrf

            <section class="card dash-section">
                <h2 class="card-title">Over de unit</h2>
                <div class="portal-field">
                    <label class="portal-label" for="title">Titel</label>
                    <input class="portal-input" id="title" name="title" value="{{ old('title') }}" maxlength="150" placeholder="Bijv. 4 gekoppelde kantoorunits 6 x 3 m" required>
                </div>
                <div class="portal-field">
                    <label class="portal-label" for="description">Beschrijving</label>
                    <textarea class="portal-textarea" id="description" name="description" rows="7" maxlength="5000" placeholder="Afmetingen, bouwjaar, staat, voorzieningen, beschikbaarheid…" required>{{ old('description') }}</textarea>
                </div>
                <div class="portal-form__row">
                    <div class="portal-field">
                        <label class="portal-label" for="location">Locatie</label>
                        <input class="portal-input" id="location" name="location" value="{{ old('location') }}" maxlength="150" placeholder="Plaats of regio" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="price">Prijs in euro's</label>
                        <input class="portal-input" type="number" id="price" name="price" value="{{ old('price') }}" min="1" step="1" inputmode="numeric" placeholder="Bijv. 12500" required>
                    </div>
                </div>
            </section>

            <section class="card dash-section">
                <h2 class="card-title">Foto's</h2>
                <div class="portal-field">
                    <label class="portal-label" for="images">Foto's (maximaal {{ \App\Models\UsedListing::MAX_IMAGES }}, jpg/png/webp tot 10 MB per foto)</label>
                    <input class="portal-input" type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <p class="portal-help">De eerste foto wordt de hoofdfoto van je advertentie.</p>
                </div>
            </section>

            <section class="card dash-section">
                <h2 class="card-title">Contactpersoon</h2>
                <p class="portal-help">Deze gegevens worden niet op de website getoond. Reacties van geïnteresseerden sturen we naar je door.</p>
                <div class="portal-field">
                    <label class="portal-label" for="contact_name">Naam</label>
                    <input class="portal-input" id="contact_name" name="contact_name" value="{{ old('contact_name', $user->name) }}" autocomplete="name" required>
                </div>
                <div class="portal-form__row">
                    <div class="portal-field">
                        <label class="portal-label" for="contact_email">E-mailadres</label>
                        <input class="portal-input" type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $user->email) }}" autocomplete="email" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="contact_phone">Telefoonnummer (optioneel)</label>
                        <input class="portal-input" type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $user->phone) }}" autocomplete="tel">
                    </div>
                </div>
            </section>

            <section class="card dash-section">
                <div class="portal-actions">
                    <button type="submit" class="btn btn-primary">Gratis aanbieden</button>
                    <a class="btn btn-ghost" href="{{ route('my-used-listings.index') }}">Annuleren</a>
                </div>
            </section>
        </form>
    </div>
</section>
@endsection
