@php($formId = 'mh-info-form-'.\Illuminate\Support\Str::random(6))
<div class="mh-info-form" id="informatie-aanvragen">
    @if (session('form_success'))
        <div class="mh-form-notice mh-form-notice--success" role="status">{{ session('form_success') }}</div>
    @else
        <form method="post" action="{{ route('forms.information') }}" class="mh-form" novalidate>
            @csrf
            <input type="hidden" name="source_url" value="{{ url()->current() }}/">
            <div class="mh-form__honeypot" aria-hidden="true">
                <label for="{{ $formId }}-website">Website</label>
                <input type="text" id="{{ $formId }}-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            @if (($attributes['title'] ?? '') !== 'none')
                <h3 class="mh-form__title">{{ $attributes['title'] ?? 'Informatie aanvragen:' }}</h3>
            @endif

            <div class="mh-form__grid">
                <div class="mh-form__field">
                    <label for="{{ $formId }}-first">Voornaam: <abbr title="verplicht">*</abbr></label>
                    <input type="text" id="{{ $formId }}-first" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name">
                    @error('first_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                </div>
                <div class="mh-form__field">
                    <label for="{{ $formId }}-last">Achternaam: <abbr title="verplicht">*</abbr></label>
                    <input type="text" id="{{ $formId }}-last" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name">
                    @error('last_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                </div>
                <div class="mh-form__field">
                    <label for="{{ $formId }}-email">E-mailadres: <abbr title="verplicht">*</abbr></label>
                    <input type="email" id="{{ $formId }}-email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')<span class="mh-form__error">{{ $message }}</span>@enderror
                </div>
                <div class="mh-form__field">
                    <label for="{{ $formId }}-phone">Telefoonnummer:</label>
                    <input type="tel" id="{{ $formId }}-phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                    @error('phone')<span class="mh-form__error">{{ $message }}</span>@enderror
                </div>
                <div class="mh-form__field mh-form__field--full">
                    <label for="{{ $formId }}-type">Type verzoek: <abbr title="verplicht">*</abbr></label>
                    <select id="{{ $formId }}-type" name="request_type" required>
                        @foreach (['Huur', 'Koop', 'Gebruikt'] as $requestType)
                            <option value="{{ $requestType }}" @selected(old('request_type', $attributes['type'] ?? null) === $requestType)>{{ $requestType }}</option>
                        @endforeach
                    </select>
                    @error('request_type')<span class="mh-form__error">{{ $message }}</span>@enderror
                </div>
                <div class="mh-form__field mh-form__field--full">
                    <label for="{{ $formId }}-message">Beschrijf jouw vraag: <abbr title="verplicht">*</abbr></label>
                    <textarea id="{{ $formId }}-message" name="message" rows="6" required>{{ old('message') }}</textarea>
                    @error('message')<span class="mh-form__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="mh-form__footer">
                <p class="mh-form__disclaimer">Je gegevens worden uitsluitend gebruikt om je vraag te beantwoorden.</p>
                <button type="submit" class="mh-btn mh-btn--accent mh-form__submit">Versturen</button>
            </div>
        </form>
    @endif
</div>
