@extends('layouts.site', ['seo' => new \App\Support\Seo(title: \App\Support\Seo::withSiteName('Inloggen'), noindex: true)])

@section('body_class', 'page mh-auth-page')

@section('content')
<main id="content" class="mh-auth">
    <div class="form-container">
        <h1>Inloggen</h1>
        @include('auth.partials.errors')

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="form-field">
                <label class="label-basis" for="email">E-mailadres</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="basis-input" required autofocus autocomplete="email">
            </div>
            <div class="form-field">
                <label class="label-basis" for="password">Wachtwoord</label>
                <input id="password" type="password" name="password" class="basis-input" required autocomplete="current-password">
            </div>
            <div class="form-field">
                <label class="portal-checkbox"><input type="checkbox" name="remember" value="1"> Ingelogd blijven</label>
            </div>
            <button class="btn btn-primary" type="submit">Inloggen</button>
        </form>

        <div class="auth-links">
            <a href="{{ route('password.request') }}">Wachtwoord vergeten?</a><br>
            <a href="{{ route('register') }}">Nog geen account? Registreer je hier</a>
        </div>
    </div>
</main>
@endsection
