@extends('layouts.portal')

@section('title', 'Account')

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => 'Account', 'crumbs' => ['Dashboard' => route('dashboard'), 'Account' => null], 'subtitle' => 'Beheer je gegevens en wachtwoord.'])

        <section class="card">
            <form method="POST" action="{{ route('account.update') }}" class="portal-form">
                @csrf
                @method('PUT')
                <div class="portal-form__row">
                    <div class="portal-field">
                        <label class="portal-label" for="name">Naam</label>
                        <input class="portal-input" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="company_name">Bedrijfsnaam</label>
                        <input class="portal-input" id="company_name" name="company_name" value="{{ old('company_name', $user->company_name) }}">
                    </div>
                </div>
                <div class="portal-form__row">
                    <div class="portal-field">
                        <label class="portal-label" for="email">E-mailadres</label>
                        <input class="portal-input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="phone">Telefoonnummer</label>
                        <input class="portal-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                    </div>
                </div>

                <h2 class="card-title">Wachtwoord wijzigen</h2>
                <div class="portal-form__row">
                    <div class="portal-field">
                        <label class="portal-label" for="current_password">Huidig wachtwoord</label>
                        <input class="portal-input" id="current_password" type="password" name="current_password" autocomplete="current-password">
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="password">Nieuw wachtwoord</label>
                        <input class="portal-input" id="password" type="password" name="password" autocomplete="new-password">
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="password_confirmation">Herhaal nieuw wachtwoord</label>
                        <input class="portal-input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                    </div>
                </div>

                <div class="portal-actions">
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                </div>
            </form>
        </section>
    </div>
</section>
@endsection
