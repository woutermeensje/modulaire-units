@php
    /**
     * Links to this page in the other languages, shown as a flag; without a translation of this page, to the home page
     * of that language. With $showName the name of the language is shown next to the flag (mobile menu).
     *
     * @var string $class
     * @var bool|null $showName
     */
    $alternates = isset($seo) ? $seo->alternates : [];
    $flags = [
        'nl' => '<svg class="mh-flag" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 9 6" aria-hidden="true"><rect width="9" height="6" fill="#21468B"/><rect width="9" height="4" fill="#fff"/><rect width="9" height="2" fill="#AE1C28"/></svg>',
        'en' => '<svg class="mh-flag" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30" aria-hidden="true"><clipPath id="mh-flag-gb-s"><path d="M0,0 v30 h60 v-30 z"/></clipPath><clipPath id="mh-flag-gb-t"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z"/></clipPath><g clip-path="url(#mh-flag-gb-s)"><path d="M0,0 v30 h60 v-30 z" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#mh-flag-gb-t)" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg>',
    ];
@endphp
@foreach (\App\Support\Locale::all() as $switchLocale)
    @continue($switchLocale === \App\Support\Locale::current())
    @php($switchLanguage = \App\Support\Locale::details($switchLocale))
    <a href="{{ $alternates[$switchLocale] ?? \App\Support\SiteUrl::path('', $switchLocale) }}" class="{{ $class }}" hreflang="{{ $switchLanguage['hreflang'] }}" lang="{{ $switchLanguage['hreflang'] }}" aria-label="{{ $switchLanguage['name'] }}" title="{{ $switchLanguage['name'] }}">{!! $flags[$switchLocale] ?? '' !!}@if ($showName ?? false)<span>{{ $switchLanguage['name'] }}</span>@endif</a>
@endforeach
