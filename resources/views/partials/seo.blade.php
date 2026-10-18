@php
    /** @var \App\Support\Seo $seo */
    $siteName = \App\Models\Setting::get('site_name');
@endphp
<title>{{ $seo->title }}</title>
@if ($seo->description)
    <meta name="description" content="{{ $seo->description }}">
@endif
<meta name="robots" content="{{ $seo->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }}">
@if ($canonical = $seo->canonicalUrl())
    <link rel="canonical" href="{{ $canonical }}">
@endif
<meta property="og:locale" content="nl_NL">
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
    '@context' => 'https://schema.org',
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
            '@id' => url('/').'/#website',
            'url' => url('/').'/',
            'name' => $siteName,
            'description' => \App\Models\Setting::get('site_tagline'),
            'publisher' => ['@id' => url('/').'/#organization'],
            'inLanguage' => 'nl-NL',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
