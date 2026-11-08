@php
    /**
     * Links to this page in the other languages; without a translation of this page, to the home page of that language.
     *
     * @var string $class
     */
    $alternates = isset($seo) ? $seo->alternates : [];
@endphp
@foreach (\App\Support\Locale::all() as $switchLocale)
    @continue($switchLocale === \App\Support\Locale::current())
    @php($switchLanguage = \App\Support\Locale::details($switchLocale))
    <a href="{{ $alternates[$switchLocale] ?? \App\Support\SiteUrl::path('', $switchLocale) }}" class="{{ $class }}" hreflang="{{ $switchLanguage['hreflang'] }}" lang="{{ $switchLanguage['hreflang'] }}" aria-label="{{ $switchLanguage['name'] }}" title="{{ $switchLanguage['name'] }}">{{ strtoupper($switchLocale) }}</a>
@endforeach
