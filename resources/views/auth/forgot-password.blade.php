@extends('layouts.portal')

@section('title', 'Wachtwoord vergeten')

@section('content')
<section class="auth-page">
    <div class="form-container">
        <h1>Wachtwoord vergeten</h1>
        <p>Vul je e-mailadres in. We sturen je een link waarmee je een nieuw wachtwoord kunt instellen.</p>
        @include('auth.partials.errors')

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="form-field">
                <label class="label-basis" for="email">E-mailadres</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="basis-input" required autofocus>
            </div>
            <button class="btn btn-primary" type="submit">Herstellink versturen</button>
        </form>

        <div class="auth-links">
            <a href="{{ route('login') }}">Terug naar inloggen</a>
        </div>
    </div>
</section>
@endsection
