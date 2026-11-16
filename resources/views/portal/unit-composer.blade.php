@extends('layouts.portal')

@section('title', $title)

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => $title, 'crumbs' => $crumbs, 'subtitle' => $subtitle])

        @if ($storeUrl ?? null)
            {{-- Admins add the composed unit to the products, for the landing pages. --}}
            <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="portal-form">
                @csrf

                <section class="card dash-section">
                    <h2 class="card-title">Over de unit</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="title">Titel</label>
                        <input class="portal-input" id="title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Bijv. Portiersloge of Kantoorunit" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="description">Beschrijving</label>
                        <textarea class="portal-textarea" id="description" name="description" rows="10">{{ old('description') }}</textarea>
                    </div>
                </section>

                <section class="card dash-section">
                    <h2 class="card-title">Prijzen</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="price_rent_week">Huurprijs per week</label>
                        <input class="portal-input" id="price_rent_week" name="price_rent_week" value="{{ old('price_rent_week') }}" maxlength="190" placeholder="Bijv. 75">
                    </div>
                    <p class="portal-help">Op de website staat dit als "€ 75 per week"; het euroteken komt er vanzelf bij.</p>
                    <div class="portal-field">
                        <label class="portal-label" for="price_buy_min">Koopprijs vanaf (incl. btw)</label>
                        <input class="portal-input" id="price_buy_min" name="price_buy_min" value="{{ old('price_buy_min') }}" inputmode="numeric" placeholder="Bijv. 4500">
                    </div>
                    <p class="portal-help">Alleen hele euro's, zonder punt of euroteken. Op de website staat dit als vanafprijs "€ 4.500 incl. btw" en de unit komt in de Google-productfeed. Leeg laten = "Op aanvraag".</p>
                </section>

                <section class="card dash-section">
                    @include('admin.fields.configurator', [
                        'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                        'record' => null,
                        'value' => $configuration,
                        'composer' => true,
                        'ai' => false,
                        'previewUrl' => $previewUrl,
                        'storeUrl' => $storeUrl,
                    ])
                </section>

                <section class="card dash-section">
                    <div class="portal-actions">
                        <button type="submit" class="btn btn-primary"><i class="ph ph-plus-circle"></i> Unit toevoegen aan producten</button>
                        <a class="btn btn-ghost" href="{{ route('my-used-listings.index') }}">Annuleren</a>
                    </div>
                </section>
            </form>
        @elseif ($sendUrl ?? null)
            {{-- Admins make a quote for a client: see Portal\QuoteComposerController. --}}
            <form method="POST" action="{{ $sendUrl }}" enctype="multipart/form-data" class="portal-form">
                @csrf

                <section class="card dash-section">
                    <h2 class="card-title">Offerte</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="title">Titel van de offerte</label>
                        <input class="portal-input" id="title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Bijv. Offerte portiersloge 6 x 3 meter" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="client_id">Opdrachtgever</label>
                        <select class="portal-input" id="client_id" name="client_id" data-search-select>
                            <option value="">Kies een opdrachtgever</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->company_name ? $client->company_name.' – '.$client->name : $client->name }} ({{ $client->email }})</option>
                            @endforeach
                        </select>
                        <p class="portal-help">Staat de opdrachtgever er niet tussen? <a href="{{ route('admin.opdrachtgevers.create') }}">Voeg een opdrachtgever toe</a>.</p>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="locale">Taal van de offerte</label>
                        <select class="portal-input" id="locale" name="locale" data-search-select>
                            @foreach ($locales as $locale => $localeName)
                                <option value="{{ $locale }}" @selected(old('locale', \App\Support\Locale::SOURCE) === $locale)>{{ $localeName }}</option>
                            @endforeach
                        </select>
                        <p class="portal-help">De PDF en de mail komen in deze taal, met de specificatie en de datum. Schrijf de titel en de begeleidende tekst zelf in dezelfde taal.</p>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="intro">Begeleidende tekst</label>
                        <textarea class="portal-textarea" id="intro" name="intro" rows="8" data-quill="off" placeholder="Beste ..., hierbij de offerte voor ...">{{ old('intro') }}</textarea>
                        <p class="portal-help">Staat bovenaan de PDF en in de mail.</p>
                    </div>
                </section>

                <section class="card dash-section">
                    @include('admin.fields.configurator', [
                        'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                        'record' => null,
                        'value' => $configuration,
                        'composer' => true,
                        'ai' => false,
                        'previewUrl' => $previewUrl,
                        'quoteExtras' => true,
                        'quoteForm' => true,
                        'pdfPreviewUrl' => $pdfPreviewUrl,
                    ])
                </section>
            </form>

            <section class="card dash-section">
                <h2 class="card-title">Verstuurde offertes</h2>
                @if ($quotes->isEmpty())
                    <p class="portal-help">Nog geen offertes verstuurd.</p>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Nummer</th><th>Titel</th><th>Taal</th><th>Opdrachtgever</th><th>Verstuurd</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($quotes as $quote)
                                    <tr>
                                        <td>{{ $quote->number() }}</td>
                                        <td>{{ $quote->title }}</td>
                                        <td>{{ $locales[$quote->locale] ?? $quote->locale }}</td>
                                        <td>{{ $quote->client->company_name ?: $quote->client->name }}</td>
                                        <td>{{ $quote->sent_at?->format('d-m-Y H:i') }}</td>
                                        <td><a href="{{ route('quotes.pdf', $quote) }}" target="_blank" rel="noopener"><i class="ph ph-file-pdf"></i> PDF</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @else
            <section class="card dash-section">
                @include('admin.fields.configurator', [
                    'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                    'record' => null,
                    'value' => $configuration,
                    'composer' => true,
                    'ai' => $ai ?? false,
                    'previewUrl' => $previewUrl,
                    'designUrl' => $designUrl ?? null,
                    'quoteUrl' => $quoteUrl ?? null,
                ])
            </section>
        @endif
    </div>
</section>
@endsection
