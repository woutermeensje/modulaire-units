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
                @if ($savedDraft ?? null)
                    <input type="hidden" name="draft_id" value="{{ $savedDraft->id }}">
                    <p class="form-alert">Concept {{ $savedDraft->number() }}, laatst opgeslagen op {{ $savedDraft->updated_at->format('d-m-Y \o\m H:i') }}. Met "Opslaan als concept" werk je dit concept bij; met "Offerte versturen" wordt het verstuurd.</p>
                @endif

                @if ($copiedFrom ?? null)
                    <p class="form-alert">Ingevuld met de gegevens van {{ $copiedFrom->number() }} ("{{ $copiedFrom->title }}"). Pas aan wat anders is; er wordt een nieuwe offerte verstuurd.</p>
                @endif

                <section class="card dash-section">
                    <h2 class="card-title">Offerte</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="title">Titel van de offerte</label>
                        <input class="portal-input" id="title" name="title" value="{{ old('title', $draft->title) }}" maxlength="255" placeholder="Bijv. Offerte portiersloge 6 x 3 meter" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="client_id">Opdrachtgever</label>
                        <select class="portal-input" id="client_id" name="client_id" data-search-select>
                            <option value="">Kies een opdrachtgever</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) old('client_id', $draft->client_id) === (string) $client->id)>{{ $client->company_name ? $client->company_name.' – '.$client->name : $client->name }} ({{ $client->email }})</option>
                            @endforeach
                        </select>
                        <p class="portal-help">Staat de opdrachtgever er niet tussen? <a href="{{ route('admin.opdrachtgevers.create') }}">Voeg een opdrachtgever toe</a>.</p>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="locale">Taal van de offerte</label>
                        <select class="portal-input" id="locale" name="locale" data-search-select>
                            @foreach ($locales as $locale => $localeName)
                                <option value="{{ $locale }}" @selected(old('locale', $draft->locale ?? \App\Support\Locale::SOURCE) === $locale)>{{ $localeName }}</option>
                            @endforeach
                        </select>
                        <p class="portal-help">De PDF en de mail komen in deze taal, met de specificatie en de datum. Schrijf de titel en de begeleidende tekst zelf in dezelfde taal.</p>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="intro">Begeleidende tekst</label>
                        <textarea class="portal-textarea" id="intro" name="intro" rows="8" data-quill="off" placeholder="Beste ..., hierbij de offerte voor ...">{{ old('intro', $draft->intro) }}</textarea>
                        <p class="portal-help">Staat bovenaan de PDF en in de mail.</p>
                    </div>
                </section>

                <section class="card dash-section">
                    <h2 class="card-title">Project</h2>
                    <div class="portal-form__row">
                        <div class="portal-field">
                            <label class="portal-label" for="kind">Koop of huur</label>
                            <select class="portal-input" id="kind" name="kind" data-quote-kind>
                                @foreach (\App\Models\Quote::KINDS as $kind => $kindLabel)
                                    <option value="{{ $kind }}" @selected(old('kind', $draft->kind ?? 'sale') === $kind)>{{ $kindLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="portal-field">
                            <label class="portal-label" for="reference">Referentie of projectnaam van de opdrachtgever</label>
                            <input class="portal-input" id="reference" name="reference" value="{{ old('reference', $draft->reference) }}" maxlength="255" placeholder="Bijv. pasuitgiftepost eindklant">
                        </div>
                    </div>
                    @php
                        // The price is kept in cents; the form shows euros.
                        $unitPrice = collect($draft->lines ?? [])->firstWhere('status', 'price')['price'] ?? null;
                    @endphp
                    <div class="portal-form__row">
                        <div class="portal-field">
                            <label class="portal-label" for="unit_price">
                                <span data-quote-rental>Huurprijs per week per unit (€)</span>
                                <span data-quote-sale>Aanschafprijs per unit (€)</span>
                            </label>
                            <input class="portal-input" id="unit_price" name="unit_price" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('unit_price', $unitPrice === null ? '' : number_format($unitPrice / 100, 2, '.', '')) }}" placeholder="Bijv. 60">
                            <p class="portal-help">Excl. btw. Bij meer units rekent de PDF het aantal units uit de samenstelling mee. Leeg laten als de prijs nog niet vaststaat.</p>
                        </div>
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
                        'draftUrl' => $draftUrl,
                    ])
                </section>

                <section class="card dash-section">
                    <h2 class="card-title">Technische specificatie</h2>
                    <p class="portal-help">Eén regel per specificatie. De regels komen in de PDF bij het onderdeel, na wat de samenstelling hierboven al invult. Lege en uitgevinkte vakken komen niet in de PDF; de tekst van een uitgevinkt vak blijft wel bewaard. Een nieuwe offerte begint met de standaardunit van Moravia; pas het aan bij een andere leverancier of een gebruikte unit. Vul alleen waarden in die je van de leverancier hebt: liever leeg dan een gok.</p>
                    @php
                        $hiddenSpecifications = old('specification_choices')
                            ? array_diff(array_keys(\App\Support\QuoteSpecification::DETAILS), old('included_specifications', []))
                            : ($draft->hidden_specifications ?? []);
                    @endphp
                    <input type="hidden" name="specification_choices" value="1">
                    <label class="portal-checkbox quote-details__all">
                        <input type="checkbox" data-quote-details-all>
                        Alles aan- of uitvinken
                    </label>
                    <div class="quote-details" data-quote-details>
                        @foreach (\App\Support\QuoteSpecification::DETAILS as $key => $detail)
                            @php
                                $included = ! in_array($key, $hiddenSpecifications, true);
                            @endphp
                            <div class="portal-field quote-details__item" @unless ($included) data-excluded @endunless>
                                <div class="quote-details__head">
                                    <label class="portal-label" for="specifications_{{ $key }}">{{ $detail['label'] }}</label>
                                    <label class="portal-checkbox" title="Uitgevinkt blijft de tekst bewaard, maar komt hij niet in de PDF">
                                        <input type="checkbox" name="included_specifications[]" value="{{ $key }}" @checked($included) data-quote-detail-toggle>
                                        In de PDF
                                    </label>
                                </div>
                                <textarea class="portal-textarea" id="specifications_{{ $key }}" name="specifications[{{ $key }}]" rows="3" data-quill="off" placeholder="{{ $detail['placeholder'] }}">{{ old("specifications.$key", $draft->specifications[$key] ?? '') }}</textarea>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="card dash-section">
                    <h2 class="card-title">Voorwaarden en notities</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="terms">Voorwaarden</label>
                        <textarea class="portal-textarea" id="terms" name="terms" rows="6" data-quill="off" placeholder="Eén voorwaarde per regel. Bijv.&#10;Levertijd: … weken na akkoord&#10;Garantie: … jaar&#10;Betaling: … binnen 14 dagen&#10;Bouwvergunning regelt de opdrachtgever, indien nodig">{{ old('terms', $draft->terms) }}</textarea>
                        <p class="portal-help">Bij huur ook: minimale huurtermijn, borg, schade, demontage en opleverstaat.</p>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="open_points">Nog te bevestigen</label>
                        <textarea class="portal-textarea" id="open_points" name="open_points" rows="4" data-quill="off" placeholder="Eén vraag of aanname per regel. Bijv.&#10;Is een toilet nodig?&#10;Welke afmeting krijgt het uitgifteluik?">{{ old('open_points', $draft->open_points) }}</textarea>
                        <p class="portal-help">Komt in de PDF als lijst met punten die de opdrachtgever nog moet bevestigen.</p>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="internal_notes">Interne notities</label>
                        <textarea class="portal-textarea" id="internal_notes" name="internal_notes" rows="4" data-quill="off" placeholder="Bijv. inkoopprijs, leverancier, marge">{{ old('internal_notes', $draft->internal_notes) }}</textarea>
                        <p class="portal-help">Alleen voor jou. Komt nooit in de PDF of de mail.</p>
                    </div>
                </section>
            </form>

            <p class="portal-help"><a href="{{ route('quotes.index') }}"><i class="ph ph-files"></i> Verstuurde offertes bekijken ({{ $quoteCount }})</a> · <a href="{{ route('quotes.index', ['status' => \App\Http\Controllers\Portal\QuoteComposerController::DRAFT_KEY]) }}"><i class="ph ph-note-pencil"></i> Concepten ({{ $draftCount }})</a>. Daar kopieer je ook een eerdere offerte als basis voor een nieuwe.</p>
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
