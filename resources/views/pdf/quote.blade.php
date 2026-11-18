@php
    /**
     * The quote PDF for a client (dompdf). Images arrive as data URIs, since dompdf can't draw the unit's SVGs itself.
     *
     * @var \App\Models\Quote $quote
     * @var array<string, array<int, string>> $specification
     * @var array<string, string> $images
     * @var string|null $logo
     */
    $client = $quote->client;
    $siteName = \App\Models\Setting::get('site_name');
    $price = $quote->price();
    $money = \App\Support\QuotePrice::format(...);
    $periodLabel = fn (array $line): string => __(\App\Support\QuotePrice::PERIODS[$line['period']] ?? 'eenmalig');
    $project = array_filter([
        __('Soort') => $quote->kind ? __(\App\Models\Quote::KINDS[$quote->kind] ?? $quote->kind) : null,
        __('Huurperiode') => $quote->isRental() && $quote->rental_weeks ? trans_choice('{1} 1 week|[2,*] :count weken', $quote->rental_weeks) : null,
        __('Referentie') => $quote->reference,
        __('Afleveradres') => $quote->delivery_address,
        __('Gewenste levering') => $quote->delivery_date,
        __('Geldig tot') => $quote->valid_until?->translatedFormat('j F Y'),
    ]);
    $openPoints = \App\Support\QuoteSpecification::lines($quote->open_points);
    $terms = \App\Support\QuoteSpecification::lines($quote->terms);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
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
        .project { width: 100%; border-collapse: collapse; margin-bottom: 8mm; }
        .project th { width: 32%; padding: 1mm 4mm 1mm 0; text-align: left; font-weight: normal; color: #5b6770; }
        .project td { padding: 1mm 0; }
        .section { margin-top: 9mm; page-break-inside: avoid; }
        .prices { width: 100%; border-collapse: collapse; }
        .prices th { padding: 2mm 2mm 2mm 0; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #5b6770; border-bottom: 0.3mm solid #dde2e6; }
        .prices td { padding: 2mm 2mm 2mm 0; vertical-align: top; border-bottom: 0.3mm solid #dde2e6; }
        .prices .amount { text-align: right; white-space: nowrap; padding-right: 0; }
        .prices .number { text-align: right; white-space: nowrap; }
        .prices tr { page-break-inside: avoid; }
        .totals { width: 60%; margin-left: 40%; margin-top: 3mm; border-collapse: collapse; }
        .totals td { padding: 1.2mm 0; }
        .totals .amount { text-align: right; white-space: nowrap; }
        .totals .grand td { padding-top: 2mm; border-top: 0.4mm solid #25486e; font-weight: bold; color: #25486e; }
        .muted { color: #5b6770; }
        .list { margin: 0; padding: 0 0 0 4mm; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #5b6770; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">{{ $siteName }} · {{ \App\Models\Setting::get('address') }} · {{ \App\Models\Setting::get('phone') }} · {{ \App\Models\Setting::get('email') }}</div>

    <table class="header">
        <tr>
            <td class="brand">
                @if ($logo ?? null)
                    <img src="{{ $logo }}" alt="">
                @endif
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
                <div class="label">{{ __('Offerte voor') }}</div>
                @if ($client->company_name)
                    <strong>{{ $client->company_name }}</strong><br>
                    {{ __('T.a.v. :name', ['name' => $client->name]) }}<br>
                @else
                    <strong>{{ $client->name }}</strong><br>
                @endif
                {{ $client->email }}
                @if ($client->phone)
                    <br>{{ $client->phone }}
                @endif
            </td>
            <td class="details">
                <div class="label">{{ __('Offertenummer') }}</div>
                {{ $quote->number() }}<br>
                <div class="label" style="margin-top: 2mm">{{ __('Datum') }}</div>
                {{ ($quote->sent_at ?? now())->translatedFormat('j F Y') }}
            </td>
        </tr>
    </table>

    <h1>{{ $quote->title }}</h1>

    @if ($project)
        <table class="project">
            @foreach ($project as $projectLabel => $projectValue)
                <tr><th>{{ $projectLabel }}</th><td>{{ $projectValue }}</td></tr>
            @endforeach
        </table>
    @endif

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
                            <div class="caption">{{ __(\App\Models\Quote::IMAGES[$key]) }}</div>
                        @endisset
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @isset($images['plan'])
        <div class="plan">
            <img src="{{ $images['plan'] }}" alt="">
            <div class="caption">{{ __(\App\Models\Quote::IMAGES['plan']) }}</div>
        </div>
    @endisset

    <div class="specification-page">
    <h2>{{ __('Specificatie: :title', ['title' => $quote->title]) }}</h2>
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

    @unless ($price->isEmpty())
        <div class="section">
            <h2>{{ __('Prijzen') }}</h2>
            @php($charged = $price->lines('price', 'included'))
            @if ($charged)
                <table class="prices">
                    <tr>
                        <th>{{ __('Omschrijving') }}</th>
                        <th class="number">{{ __('Aantal') }}</th>
                        <th class="number">{{ __('Prijs') }}</th>
                        <th class="amount">{{ __('Totaal') }}</th>
                    </tr>
                    @foreach ($charged as $line)
                        <tr>
                            <td>{{ $line['description'] }}</td>
                            <td class="number">{{ $line['quantity'] }}</td>
                            @if ($line['status'] === 'included')
                                <td class="number muted" colspan="2" style="text-align: right">{{ __('Inbegrepen') }}</td>
                            @else
                                <td class="number">{{ $money($line['price']) }} {{ $periodLabel($line) }}</td>
                                <td class="amount">{{ $money(\App\Support\QuotePrice::lineTotal($line)) }} {{ $periodLabel($line) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </table>

                <table class="totals">
                    @if ($price->oneOff() > 0)
                        <tr><td>{{ __('Eenmalige kosten') }}</td><td class="amount">{{ $money($price->oneOff()) }}</td></tr>
                    @endif
                    @if ($price->weekly() > 0)
                        <tr><td>{{ __('Huur per week') }}</td><td class="amount">{{ $money($price->weekly()) }}</td></tr>
                        @if ($price->rental() !== null)
                            <tr><td>{{ __('Huur over :weeks', ['weeks' => trans_choice('{1} 1 week|[2,*] :count weken', $price->rentalWeeks())]) }}</td><td class="amount">{{ $money($price->rental()) }}</td></tr>
                        @endif
                    @endif
                    @php($rentLeftOut = $price->weekly() > 0 && $price->rental() === null)
                    <tr><td>{{ $rentLeftOut ? __('Totaal eenmalig excl. btw') : __('Totaal excl. btw') }}</td><td class="amount">{{ $money($price->totalExcludingVat()) }}</td></tr>
                    <tr><td>{{ __('Btw :rate%', ['rate' => \App\Support\QuotePrice::VAT_RATE]) }}</td><td class="amount">{{ $money($price->vat()) }}</td></tr>
                    <tr class="grand"><td>{{ $rentLeftOut ? __('Totaal eenmalig incl. btw') : __('Totaal incl. btw') }}</td><td class="amount">{{ $money($price->totalIncludingVat()) }}</td></tr>
                </table>
            @endif

            @if ($optional = $price->lines('optional'))
                <h2 style="margin-top: 7mm">{{ __('Opties (niet in het totaal)') }}</h2>
                <table class="prices">
                    @foreach ($optional as $line)
                        <tr>
                            <td>{{ $line['description'] }}</td>
                            <td class="number">{{ $line['quantity'] }}×</td>
                            <td class="amount">{{ $money(\App\Support\QuotePrice::lineTotal($line)) }} {{ $periodLabel($line) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($open = $price->lines('tbd'))
                <h2 style="margin-top: 7mm">{{ __('Nog te bepalen kosten') }}</h2>
                <ul class="list">
                    @foreach ($open as $line)
                        <li>{{ $line['description'] }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($byClient = $price->lines('client'))
                <h2 style="margin-top: 7mm">{{ __('Niet inbegrepen, door de opdrachtgever te regelen') }}</h2>
                <ul class="list">
                    @foreach ($byClient as $line)
                        <li>{{ $line['description'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endunless

    @if ($openPoints)
        <div class="section">
            <h2>{{ __('Nog te bevestigen') }}</h2>
            <ul class="list">
                @foreach ($openPoints as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($terms)
        <div class="section">
            <h2>{{ __('Voorwaarden') }}</h2>
            <ul class="list">
                @foreach ($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    </div>
</body>
</html>
