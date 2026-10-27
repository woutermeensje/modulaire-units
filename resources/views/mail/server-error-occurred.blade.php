<x-mail::message>
# Serverfout op de website

Er is een onverwachte fout opgetreden. Dezelfde fout wordt maximaal één keer per {{ \App\Services\ErrorNotifier::THROTTLE_SECONDS / 60 }} minuten gemaild.

**Fout:** {{ $exceptionClass }}<br>
**Melding:** {{ $exceptionMessage ?: '(geen melding)' }}<br>
**Locatie:** {{ $location }}<br>
@if ($url)
**URL:** {{ $method }} {{ $url }}<br>
@endif
@if ($ip)
**IP-adres:** {{ $ip }}<br>
@endif
**Tijdstip:** {{ now()->timezone('Europe/Amsterdam')->format('d-m-Y H:i:s') }}

<x-mail::panel>
{{ $trace }}
</x-mail::panel>
</x-mail::message>
