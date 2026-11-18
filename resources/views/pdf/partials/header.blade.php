{{-- The top of every quote PDF: the logo on the left, the company and contact details on the right. Needs the .header, .brand and .company styles of the PDF. --}}
@php
    $website = preg_replace('#^www\.#', '', (string) parse_url(\App\Support\SiteUrl::absolute('/'), PHP_URL_HOST));
@endphp
<table class="header">
    <tr>
        <td class="brand">
            @if ($logo ?? null)
                <img src="{{ $logo }}" alt="">
            @endif
            <span class="modulaire">Modulaire</span><span class="huisvesting">Huisvesting</span>
        </td>
        <td class="company">
            {{ \App\Models\Setting::get('site_name') }}<br>
            {{ \App\Models\Setting::get('address') }}<br>
            {{ \App\Models\Setting::get('phone') }}<br>
            {{ \App\Models\Setting::get('email') }}<br>
            {{ $website }}
        </td>
    </tr>
</table>
