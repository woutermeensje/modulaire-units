@extends('layouts.portal')

@php
    $draftKey = \App\Http\Controllers\Portal\QuoteComposerController::DRAFT_KEY;
    $status = $drafts ? ['status' => $draftKey] : [];
@endphp

@section('title', $drafts ? 'Concepten' : 'Verstuurde offertes')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => $drafts ? 'Concepten' : 'Verstuurde offertes',
            'crumbs' => ['Account' => route('dashboard'), ($drafts ? 'Concepten' : 'Verstuurde offertes') => null],
            'subtitle' => $drafts
                ? 'Offertes die je hebt opgeslagen maar nog niet verstuurd, laatst bewerkt eerst. Met Bewerken ga je verder waar je was.'
                : 'Alle offertes die je vanuit de offertetool hebt verstuurd, nieuwste eerst. Met Kopieer begin je een nieuwe offerte met dezelfde gegevens.',
        ])

        <section class="card dash-section">
            <div class="portal-filters">
                <a class="btn {{ $drafts ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('quotes.index') }}"><i class="ph ph-paper-plane-tilt"></i> Verstuurd</a>
                <a class="btn {{ $drafts ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('quotes.index', ['status' => $draftKey]) }}"><i class="ph ph-note-pencil"></i> Concepten ({{ $draftCount }})</a>
            </div>
            <div class="portal-filters">
                <a class="btn {{ $kind ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('quotes.index', $status) }}">Alles</a>
                @foreach (\App\Models\Quote::KINDS as $key => $label)
                    <a class="btn {{ $kind === $key ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('quotes.index', [...$status, 'soort' => $key]) }}">{{ $label }}</a>
                @endforeach
                <a class="btn btn-secondary" href="{{ route('quote-composer') }}"><i class="ph ph-plus"></i> Nieuwe offerte</a>
            </div>
        </section>

        <section class="card dash-section">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nummer</th><th>{{ $drafts ? 'Laatst bewerkt' : 'Verstuurd' }}</th><th>Titel</th><th>Opdrachtgever</th><th>Soort</th><th>Totaal excl. btw</th><th>Taal</th><th>Acties</th></tr></thead>
                    <tbody>
                        @forelse ($quotes as $quote)
                            @php($price = $quote->price())
                            <tr>
                                <td>{{ $quote->number() }}</td>
                                <td>{{ ($drafts ? $quote->updated_at : $quote->sent_at)?->format('d-m-Y H:i') }}</td>
                                <td><strong>{{ $quote->title }}</strong>@if ($quote->reference)<br>{{ $quote->reference }}@endif</td>
                                <td>
                                    @if ($quote->client)
                                        {{ $quote->client->company_name ?: $quote->client->name }}<br><a href="mailto:{{ $quote->client->email }}">{{ $quote->client->email }}</a>
                                    @else
                                        Nog niet gekozen
                                    @endif
                                </td>
                                <td>
                                    {{ \App\Models\Quote::KINDS[$quote->kind] ?? $quote->kind }}
                                </td>
                                <td>
                                    @if ($price->isEmpty())
                                        –
                                    @else
                                        @if ($price->oneOff() > 0 || $price->weekly() === 0)
                                            {{ \App\Support\QuotePrice::format($price->totalExcludingVat()) }} eenmalig<br>
                                        @endif
                                        @if ($price->weekly() > 0)
                                            {{ \App\Support\QuotePrice::format($price->weekly()) }} per week
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $locales[$quote->locale] ?? $quote->locale }}</td>
                                <td>
                                    <div class="admin-actions">
                                        @if ($drafts)
                                            <a class="btn btn-ghost" href="{{ route('quote-composer', [$draftKey => $quote->id]) }}"><i class="ph ph-pencil-simple"></i> Bewerken</a>
                                            <form method="POST" action="{{ route('quotes.destroy', $quote) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-ghost" data-confirm="Concept &quot;{{ $quote->title }}&quot; verwijderen?"><i class="ph ph-trash"></i> Verwijderen</button>
                                            </form>
                                        @else
                                            <a class="btn btn-ghost" href="{{ route('quotes.pdf', $quote) }}" target="_blank" rel="noopener"><i class="ph ph-file-pdf"></i> PDF</a>
                                        @endif
                                        <a class="btn btn-ghost" href="{{ route('quote-composer', [\App\Http\Controllers\Portal\QuoteComposerController::COPY_KEY => $quote->id]) }}"><i class="ph ph-copy"></i> Kopieer</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8">{{ $kind ? 'Geen offertes van deze soort.' : ($drafts ? 'Geen concepten.' : 'Nog geen offertes verstuurd.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $quotes->links() }}</div>
        </section>
    </div>
</section>
@endsection
