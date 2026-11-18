<x-mail::message>
# {{ $quote->title }}

Beste {{ $quote->client->firstName() }},

In de bijlage vind je de offerte {{ $quote->number() }}. Heb je vragen? Beantwoord deze mail of bel ons op {{ \App\Models\Setting::get('phone') }}.

{{ $siteName }}

<x-slot:signature>
{{ $siteName }} · {{ \App\Models\Setting::get('phone') }} · {{ \App\Models\Setting::get('email') }}
</x-slot:signature>
</x-mail::message>
