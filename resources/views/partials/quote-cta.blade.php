@php
    $phone = \App\Models\Setting::get('phone');
    $email = \App\Models\Setting::get('email');
@endphp
<div class="mh-quote-cta">
    <hr class="mh-quote-cta__divider">
    <p class="mh-quote-cta__label">Interesse in deze {{ $type === 'unit' ? 'unit' : 'oplossing' }}?</p>
    @if (session('quote_added'))
        <p class="mh-quote-cta__notice" role="status">Toegevoegd aan je offerte. <a href="{{ route('quote.show') }}/">Bekijk mijn offerte</a></p>
    @endif
    <form method="post" action="{{ route('quote.add') }}" class="mh-quote-fallback" id="mh-quote-form">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="id" value="{{ $id }}">
        <div class="mh-qty-stepper">
            <button type="button" class="mh-qty-btn mh-qty-btn--minus" aria-label="Minder">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <input type="number" class="mh-qty-input" name="quantity" value="1" min="1" aria-label="Aantal">
            <button type="button" class="mh-qty-btn mh-qty-btn--plus" aria-label="Meer">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
        </div>
        <button type="submit" class="mh-quote-fallback-btn">Toevoegen aan offerte</button>
    </form>
</div>
<div class="mh-contact-info">
    <a href="mailto:{{ $email }}" class="mh-contact-item">{{ $email }}</a>
    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="mh-contact-item">{{ $phone }}</a>
</div>
