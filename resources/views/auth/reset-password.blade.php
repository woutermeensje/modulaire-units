@extends('layouts.site', ['seo' => new \App\Support\Seo(title: \App\Support\Seo::withSiteName('Nieuw wachtwoord instellen'), noindex: true)])

@section('body_class', 'page mh-auth-page')

@section('content')
<main id="content" class="mh-auth">
    <div class="form-container">
        <h1>Nieuw wachtwoord instellen</h1>
        @include('auth.partials.errors')

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="form-field">
                <label class="label-basis" for="email">E-mailadres</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="basis-input" required>
            </div>
            <div class="form-field">
                <label class="label-basis" for="password">Nieuw wachtwoord</label>
                <input id="password" type="password" name="password" class="basis-input" required autocomplete="new-password">
            </div>
            <div class="form-field">
                <label class="label-basis" for="password_confirmation">Herhaal wachtwoord</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="basis-input" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary" type="submit">Wachtwoord opslaan</button>
        </form>
    </div>
</main>
@endsection
