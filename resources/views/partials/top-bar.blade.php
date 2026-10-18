{{-- Balk boven de header: contactgegevens links, partners en netwerk rechts (config/modulaire.php). --}}
@php
    $phone = \App\Models\Setting::get('phone');
    $email = \App\Models\Setting::get('email');
    $groups = array_filter([
        ['label' => 'Onze partners', 'items' => config('modulaire.partners', [])],
        ['label' => 'Ons netwerk', 'items' => config('modulaire.network', [])],
    ], fn (array $group): bool => ! empty($group['items']));
@endphp

<aside class="mh-top-bar" aria-label="Contact, partners en netwerk">
    <div class="mh-top-bar__inner">
        <ul class="mh-top-bar__list">
            @if ($phone)
                <li class="mh-top-bar__item"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="mh-top-bar__link">{{ $phone }}</a></li>
            @endif
            @if ($email)
                <li class="mh-top-bar__item"><a href="mailto:{{ $email }}" class="mh-top-bar__link">{{ $email }}</a></li>
            @endif
        </ul>

        @foreach ($groups as $group)
            <div class="mh-top-bar__group">
                <span class="mh-top-bar__label">{{ $group['label'] }}:</span>
                <ul class="mh-top-bar__list">
                    @foreach ($group['items'] as $name => $url)
                        <li class="mh-top-bar__item"><a href="{{ $url }}" class="mh-top-bar__link" target="_blank" rel="noopener">{{ $name }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</aside>
