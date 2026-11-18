@extends('layouts.portal')

@section('title', 'Tekst offerte')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => 'Tekst offerte',
            'crumbs' => ['Account' => route('dashboard'), 'Tekst offerte' => null],
            'subtitle' => 'Typ een offerte zelf uit, zonder unit samen te stellen. De opdrachtgever krijgt een mail met de offerte als PDF, met bovenaan het logo en de bedrijfsgegevens.',
        ])

        {{-- See Portal\TextQuoteController. --}}
        <form method="POST" action="{{ route('text-quotes.send') }}" class="portal-form">
            @csrf
            <section class="card dash-section">
                <div class="portal-field">
                    <label class="portal-label" for="title">Titel</label>
                    <input class="portal-input" id="title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Bijv. Offerte huur twee kantoorunits" required>
                </div>
                <div class="portal-field">
                    <label class="portal-label" for="body">Tekst</label>
                    <textarea class="portal-textarea" id="body" name="body" data-editor-height="420" placeholder="Beste ..., hierbij de offerte voor ..." required>{{ old('body') }}</textarea>
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
                <div class="portal-actions">
                    <button type="submit" class="btn btn-primary" data-confirm="Offerte versturen naar de gekozen opdrachtgever?"><i class="ph ph-paper-plane-tilt"></i> Offerte versturen</button>
                    <button type="submit" class="btn btn-ghost" formaction="{{ route('text-quotes.preview') }}" formtarget="_blank"><i class="ph ph-file-pdf"></i> Voorbeeld PDF</button>
                </div>
            </section>
        </form>

        <section class="card dash-section">
            <h2 class="card-title">Verstuurde tekst offertes</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nummer</th><th>Verstuurd</th><th>Titel</th><th>Opdrachtgever</th><th>Acties</th></tr></thead>
                    <tbody>
                        @forelse ($sentQuotes as $quote)
                            <tr>
                                <td>{{ $quote->number() }}</td>
                                <td>{{ $quote->sent_at?->format('d-m-Y H:i') }}</td>
                                <td><strong>{{ $quote->title }}</strong></td>
                                <td>
                                    @if ($quote->client)
                                        {{ $quote->client->company_name ?: $quote->client->name }}<br><a href="mailto:{{ $quote->client->email }}">{{ $quote->client->email }}</a>
                                    @endif
                                </td>
                                <td><a class="btn btn-ghost" href="{{ route('text-quotes.pdf', $quote) }}" target="_blank" rel="noopener"><i class="ph ph-file-pdf"></i> PDF</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Nog geen tekst offertes verstuurd.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $sentQuotes->links() }}</div>
        </section>
    </div>
</section>
@endsection
