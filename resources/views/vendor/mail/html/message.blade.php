<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="url('/')">
{{ \App\Models\Setting::get('site_name', config('app.name')) }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{-- A mail to a client (e.g. a quote) gives its own closing line; internal notifications say they are automatic. --}}
@isset($signature)
{!! $signature !!}
@else
Automatische melding - {{ \App\Models\Setting::get('site_name', config('app.name')) }}
@endisset
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
