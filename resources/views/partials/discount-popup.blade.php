@if ($discountCode = \App\Models\Setting::get('discount_popup_code'))
<dialog class="mh-discount-popup" id="mh-discount-popup" data-code="{{ $discountCode }}" aria-labelledby="mh-discount-popup-title">
    <button type="button" class="mh-discount-popup__close" data-discount-close aria-label="Pop-up sluiten">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h2 class="mh-discount-popup__title" id="mh-discount-popup-title">{{ \App\Models\Setting::get('discount_popup_title') }}</h2>
    <p class="mh-discount-popup__text">{{ \App\Models\Setting::get('discount_popup_text') }}</p>
    <div class="mh-discount-popup__code">
        <span class="mh-discount-popup__code-value">{{ $discountCode }}</span>
        <button type="button" class="mh-discount-popup__copy" data-discount-copy>Kopieer</button>
    </div>
    <a href="{{ url('offerte-aanvragen') }}/" class="mh-btn mh-btn--accent mh-discount-popup__btn" data-discount-close>Offerte aanvragen</a>
</dialog>
@endif
