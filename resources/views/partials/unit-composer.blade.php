@php
    /**
     * The public unit composer (no AI), shared by /unit-samensteller/ and the home page.
     *
     * @var array<string, mixed> $configuration
     */
@endphp
@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    @vite(['resources/css/unit-composer.css', 'resources/js/unit-composer.js'])
@endpush

@include('admin.fields.configurator', [
    'field' => \App\Admin\Field::make('configuration', 'Jouw samenstelling', 'configurator'),
    'record' => null,
    'value' => $configuration,
    'composer' => true,
    'ai' => false,
    'previewUrl' => route('unit-composer.public.preview'),
    'quoteUrl' => route('unit-composer.quote'),
])
