@php
    /** @var \App\Support\Seo $seo */
    $siteName = \App\Models\Setting::get('site_name');
    $language = \App\Support\Locale::details();
    $homeUrl = \App\Support\SiteUrl::absolute(\App\Support\SiteUrl::path(''));
    $alternateUrls = $seo->alternateUrls();
@endphp
<title>{{ $seo->title }}</title>
@if ($seo->description)
    <meta name="description" content="{{ $seo->description }}">
@endif
<meta name="robots" content="{{ $seo->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }}">
@if ($canonical = $seo->canonicalUrl())
    <link rel="canonical" href="{{ $canonical }}">
@endif
@foreach ($alternateUrls as $alternateLocale => $alternateUrl)
    <link rel="alternate" hreflang="{{ \App\Support\Locale::details($alternateLocale)['hreflang'] }}" href="{{ $alternateUrl }}">
@endforeach
@if (isset($alternateUrls[\App\Support\Locale::SOURCE]))
    <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls[\App\Support\Locale::SOURCE] }}">
@endif
<meta property="og:locale" content="{{ $language['og'] }}">
@foreach (array_keys($alternateUrls) as $alternateLocale)
    @if ($alternateLocale !== \App\Support\Locale::current())
        <meta property="og:locale:alternate" content="{{ \App\Support\Locale::details($alternateLocale)['og'] }}">
    @endif
@endforeach
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->title }}">
@if ($seo->description)
    <meta property="og:description" content="{{ $seo->description }}">
@endif
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
@if ($image = $seo->imageUrl())
    <meta property="og:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<script type="application/ld+json">{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => url('/').'/#organization',
            'name' => $siteName,
            'url' => url('/').'/',
            'logo' => asset('images/logo.svg'),
            'email' => \App\Models\Setting::get('email'),
            'telephone' => \App\Models\Setting::get('phone'),
        ],
        [
            '@type' => 'WebSite',
            '@id' => $homeUrl.'#website',
            'url' => $homeUrl,
            'name' => $siteName,
            'description' => \App\Models\Setting::get('site_tagline'),
            'publisher' => ['@id' => url('/').'/#organization'],
            'inLanguage' => \App\Support\Locale::isSource() ? 'nl-NL' : $language['hreflang'],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
