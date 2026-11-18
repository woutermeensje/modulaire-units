@extends('layouts.portal')

@section('title', 'Verstuurde offertes')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => 'Verstuurde offertes',
            'crumbs' => ['Account' => route('dashboard'), 'Verstuurde offertes' => null],
            'subtitle' => 'Alle offertes die je vanuit de offertetool hebt verstuurd, nieuwste eerst. Met Kopieer begin je een nieuwe offerte met dezelfde gegevens.',
        ])

        <section class="card dash-section">
            <div class="portal-filters">
                <a class="btn {{ $kind ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('quotes.index') }}">Alles</a>
                @foreach (\App\Models\Quote::KINDS as $key => $label)
                    <a class="btn {{ $kind === $key ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('quotes.index', ['soort' => $key]) }}">{{ $label }}</a>
                @endforeach
                <a class="btn btn-secondary" href="{{ route('quote-composer') }}"><i class="ph ph-plus"></i> Nieuwe offerte</a>
            </div>
        </section>

        <section class="card dash-section">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nummer</th><th>Verstuurd</th><th>Titel</th><th>Opdrachtgever</th><th>Soort</th><th>Totaal excl. btw</th><th>Taal</th><th>Acties</th></tr></thead>
                    <tbody>
                        @forelse ($quotes as $quote)
                            @php($price = $quote->price())
                            <tr>
                                <td>{{ $quote->number() }}</td>
                                <td>{{ $quote->sent_at?->format('d-m-Y H:i') }}</td>
                                <td><strong>{{ $quote->title }}</strong>@if ($quote->reference)<br>{{ $quote->reference }}@endif</td>
                                <td>{{ $quote->client->company_name ?: $quote->client->name }}<br><a href="mailto:{{ $quote->client->email }}">{{ $quote->client->email }}</a></td>
                                <td>
                                    {{ \App\Models\Quote::KINDS[$quote->kind] ?? $quote->kind }}
                                    @if ($quote->isRental() && $quote->rental_weeks)
                                        <br>{{ $quote->rental_weeks }} weken
                                    @endif
                                </td>
                                <td>
                                    @if ($price->isEmpty())
                                        –
                                    @else
                                        {{ \App\Support\QuotePrice::format($price->totalExcludingVat()) }}
                                        @if ($price->weekly() > 0)
                                            <br>waarvan {{ \App\Support\QuotePrice::format($price->weekly()) }} per week
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $locales[$quote->locale] ?? $quote->locale }}</td>
                                <td>
                                    <div class="admin-actions">
                                        <a class="btn btn-ghost" href="{{ route('quotes.pdf', $quote) }}" target="_blank" rel="noopener"><i class="ph ph-file-pdf"></i> PDF</a>
                                        <a class="btn btn-ghost" href="{{ route('quote-composer', [\App\Http\Controllers\Portal\QuoteComposerController::COPY_KEY => $quote->id]) }}"><i class="ph ph-copy"></i> Kopieer</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8">{{ $kind ? 'Geen offertes van deze soort.' : 'Nog geen offertes verstuurd.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $quotes->links() }}</div>
        </section>
    </div>
</section>
@endsection
