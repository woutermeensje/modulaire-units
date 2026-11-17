<x-mail::message>
# {{ $quote->title }}

@if (filled($quote->intro))
{!! nl2br(e($quote->intro)) !!}
@else
{{ __('Beste :name,', ['name' => $quote->client->firstName()]) }}

{{ __('In de bijlage vind je de offerte :number.', ['number' => $quote->number()]) }}
@endif

<x-mail::button :url="$quote->composerUrl()">
{{ __('Bekijk de unit in 3D') }}
</x-mail::button>

<small>{{ __('De offerte met tekeningen en de volledige specificatie staat in de bijlage (:file).', ['file' => $quote->number().'.pdf']) }}</small>

{{ $siteName }}

<x-slot:signature>
{{ $siteName }} · {{ \App\Models\Setting::get('phone') }} · {{ \App\Models\Setting::get('email') }}
</x-slot:signature>
</x-mail::message>
