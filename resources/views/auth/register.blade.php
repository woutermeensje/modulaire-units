@extends('layouts.site', ['seo' => new \App\Support\Seo(title: \App\Support\Seo::withSiteName('Account aanmaken'), noindex: true)])

@section('body_class', 'page mh-auth-page')

@section('content')
<main id="content" class="mh-auth">
    <div class="form-container form-container--wide">
        <h1>Account aanmaken</h1>
        @include('auth.partials.errors')

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <section class="form-section">Jouw gegevens</section>
            <div class="form-grid">
                <div class="form-field">
                    <label class="label-basis" for="first_name">Voornaam</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" class="basis-input" required autocomplete="given-name">
                </div>
                <div class="form-field">
                    <label class="label-basis" for="last_name">Achternaam</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" class="basis-input" required autocomplete="family-name">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="label-basis" for="company_name">Bedrijfsnaam (optioneel)</label>
                    <input id="company_name" name="company_name" value="{{ old('company_name') }}" class="basis-input" autocomplete="organization">
                </div>
                <div class="form-field">
                    <label class="label-basis" for="phone">Telefoonnummer</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" class="basis-input" autocomplete="tel">
                </div>
            </div>

            <section class="form-section">Inloggegevens</section>
            <div class="form-field">
                <label class="label-basis" for="email">E-mailadres</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="basis-input" required autocomplete="email">
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="label-basis" for="password">Wachtwoord</label>
                    <input id="password" type="password" name="password" class="basis-input" required autocomplete="new-password">
                </div>
                <div class="form-field">
                    <label class="label-basis" for="password_confirmation">Herhaal wachtwoord</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="basis-input" required autocomplete="new-password">
                </div>
            </div>
            <div class="form-field">
                <label class="portal-checkbox">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                    <span>Ik ga akkoord met de <a href="{{ url('algemene-voorwaarden') }}/" target="_blank" rel="noopener">algemene voorwaarden</a></span>
                </label>
            </div>
            <div class="portal-actions">
                <button class="btn btn-primary" type="submit">Account aanmaken</button>
            </div>
        </form>

        <div class="auth-links">
            <a href="{{ route('login') }}">Heb je al een account? Log in</a>
        </div>
    </div>
</main>
@endsection
