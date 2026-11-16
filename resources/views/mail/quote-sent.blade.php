<x-mail::message>
# {{ $quote->title }}

@if (filled($quote->intro))
{!! nl2br(e($quote->intro)) !!}
@else
Beste {{ $quote->client->firstName() }},

In de bijlage vind je de offerte {{ $quote->number() }}.
@endif

<small>De offerte met tekeningen en de volledige specificatie staat in de bijlage ({{ $quote->number() }}.pdf).</small>

{{ $siteName }}
</x-mail::message>
