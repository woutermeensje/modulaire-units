@php
    /**
     * The quote PDF for a client (dompdf). Images arrive as data URIs, since dompdf can't draw the unit's SVGs itself.
     *
     * @var \App\Models\Quote $quote
     * @var array<string, array<int, string>> $specification
     * @var array<string, string> $images
     */
    $client = $quote->client;
    $siteName = \App\Models\Setting::get('site_name');
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->title }}</title>
    <style>
        @page { margin: 22mm 18mm 24mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.5; color: #1f2a33; }
        h1 { margin: 0 0 4mm; font-size: 19pt; line-height: 1.25; color: #25486e; }
        h2 { margin: 0 0 3mm; font-size: 12pt; color: #25486e; }
        /* The list starts on a page of its own, so its heading never stays behind under the drawings. */
        .specification-page { page-break-before: always; }
        .header { width: 100%; margin-bottom: 12mm; border-collapse: collapse; }
        .header td { vertical-align: top; padding: 0; }
        .brand img { width: 13mm; vertical-align: middle; }
        .brand span { font-size: 15pt; font-weight: bold; vertical-align: middle; }
        .brand .modulaire { color: #25486e; }
        .brand .huisvesting { color: #4589ad; }
        .company { text-align: right; font-size: 8.5pt; color: #5b6770; }
        .meta { width: 100%; margin-bottom: 9mm; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 0; width: 50%; }
        .label { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #5b6770; }
        .details { text-align: right; }
        .intro { margin-bottom: 8mm; }
        .images { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .images td { width: 50%; padding: 0 2mm 4mm 0; vertical-align: top; }
        .images td + td { padding: 0 0 4mm 2mm; }
        .images img, .plan img { width: 100%; border: 0.3mm solid #dde2e6; }
        .caption { font-size: 8pt; color: #5b6770; }
        .plan { margin-bottom: 4mm; page-break-inside: avoid; }
        .specification { width: 100%; border-collapse: collapse; }
        .specification th { width: 32%; padding: 2.5mm 4mm 2.5mm 0; text-align: left; vertical-align: top; font-size: 9.5pt; color: #25486e; border-top: 0.3mm solid #dde2e6; }
        .specification td { padding: 2.5mm 0; vertical-align: top; border-top: 0.3mm solid #dde2e6; }
        .specification ul { margin: 0; padding: 0 0 0 4mm; }
        .specification tr { page-break-inside: avoid; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #5b6770; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">{{ $siteName }} · {{ \App\Models\Setting::get('address') }} · {{ \App\Models\Setting::get('phone') }} · {{ \App\Models\Setting::get('email') }}</div>

    <table class="header">
        <tr>
            <td class="brand">
                <img src="{{ public_path('images/favicon-270x270.png') }}" alt="">
                <span class="modulaire">Modulaire</span><span class="huisvesting">Huisvesting</span>
            </td>
            <td class="company">
                {{ $siteName }}<br>
                {{ \App\Models\Setting::get('address') }}<br>
                {{ \App\Models\Setting::get('phone') }}<br>
                {{ \App\Models\Setting::get('email') }}
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Offerte voor</div>
                @if ($client->company_name)
                    <strong>{{ $client->company_name }}</strong><br>
                    T.a.v. {{ $client->name }}<br>
                @else
                    <strong>{{ $client->name }}</strong><br>
                @endif
                {{ $client->email }}
                @if ($client->phone)
                    <br>{{ $client->phone }}
                @endif
            </td>
            <td class="details">
                <div class="label">Offertenummer</div>
                {{ $quote->number() }}<br>
                <div class="label" style="margin-top: 2mm">Datum</div>
                {{ ($quote->sent_at ?? now())->translatedFormat('j F Y') }}
            </td>
        </tr>
    </table>

    <h1>{{ $quote->title }}</h1>

    @if (filled($quote->intro))
        <div class="intro">{!! nl2br(e($quote->intro)) !!}</div>
    @endif

    @if (isset($images['front']) || isset($images['back']))
        <table class="images">
            <tr>
                @foreach (['front', 'back'] as $key)
                    <td>
                        @isset($images[$key])
                            <img src="{{ $images[$key] }}" alt="">
                            <div class="caption">{{ \App\Models\Quote::IMAGES[$key] }}</div>
                        @endisset
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @isset($images['plan'])
        <div class="plan">
            <img src="{{ $images['plan'] }}" alt="">
            <div class="caption">{{ \App\Models\Quote::IMAGES['plan'] }}</div>
        </div>
    @endisset

    <div class="specification-page">
    <h2>Specificatie: {{ $quote->title }}</h2>
    <table class="specification">
        @foreach ($specification as $section => $lines)
            <tr>
                <th>{{ $section }}</th>
                <td>
                    @if (count($lines) === 1)
                        {{ $lines[0] }}
                    @else
                        <ul>
                            @foreach ($lines as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    </div>
</body>
</html>
