@php
    /**
     * The PDF of a quote typed by hand in /account/tekst-offerte/ (dompdf): the logo and company details, the client,
     * the number and date, the title and the text from the rich text editor (cleaned by RichText::clean()).
     *
     * @var \App\Models\TextQuote $quote
     * @var string|null $logo
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
        h1 { margin: 0 0 6mm; font-size: 19pt; line-height: 1.25; color: #25486e; }
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
        .body h2 { margin: 6mm 0 2mm; font-size: 12pt; color: #25486e; }
        .body h3 { margin: 5mm 0 2mm; font-size: 10.5pt; color: #25486e; }
        /* Like the editor: every line is its own <p> without space between, an empty line is an empty <p>. */
        .body p { margin: 0; }
        .body ul, .body ol { margin: 0; padding: 0 0 0 6mm; }
        .body li { margin: 0; }
        .body a { color: #25486e; }
        .body .ql-indent-1 { margin-left: 6mm; }
        .body .ql-indent-2 { margin-left: 12mm; }
        .body .ql-indent-3 { margin-left: 18mm; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #5b6770; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">{{ $siteName }} · {{ \App\Models\Setting::get('address') }} · {{ \App\Models\Setting::get('phone') }} · {{ \App\Models\Setting::get('email') }}</div>

    @include('pdf.partials.header')

    <table class="meta">
        <tr>
            <td>
                @if ($client)
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
                @endif
            </td>
            <td class="details">
                <div class="label">Offertenummer</div>
                {{ $quote->id ? $quote->number() : 'Voorbeeld' }}<br>
                <div class="label" style="margin-top: 2mm">Datum</div>
                {{ ($quote->sent_at ?? now())->locale('nl')->translatedFormat('j F Y') }}
            </td>
        </tr>
    </table>

    <h1>{{ $quote->title }}</h1>

    {{-- Cleaned with RichText::clean(): only the editor's own formats are left. --}}
    <div class="body">{!! $quote->body !!}</div>
</body>
</html>
