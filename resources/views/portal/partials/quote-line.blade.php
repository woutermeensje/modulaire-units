@php
    /**
     * One price line in the quote tool; see QuotePrice. Also used as the template for "Regel toevoegen" with index
     * "__INDEX__", which the JavaScript replaces.
     *
     * @var int|string $index
     * @var array{description: string, quantity: int|string|null, price: string|null, period: string, status: string} $line
     */
@endphp
<tr data-quote-line>
    <td><input class="portal-input" name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" maxlength="255" aria-label="Omschrijving" placeholder="Bijv. Transport naar de locatie"></td>
    <td><input class="portal-input quote-lines__number" name="lines[{{ $index }}][quantity]" type="number" inputmode="numeric" min="1" max="9999" value="{{ $line['quantity'] ?? 1 }}" aria-label="Aantal"></td>
    <td><input class="portal-input quote-lines__number" name="lines[{{ $index }}][price]" type="number" inputmode="decimal" min="0" step="0.01" value="{{ $line['price'] ?? '' }}" aria-label="Prijs per stuk in euro, excl. btw"></td>
    <td data-quote-rental>
        <select class="portal-input" name="lines[{{ $index }}][period]" aria-label="Per">
            @foreach (\App\Support\QuotePrice::PERIODS as $period => $periodLabel)
                <option value="{{ $period }}" @selected(($line['period'] ?? 'once') === $period)>{{ $periodLabel }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select class="portal-input" name="lines[{{ $index }}][status]" aria-label="Status">
            @foreach (\App\Support\QuotePrice::STATUSES as $status => $statusLabel)
                <option value="{{ $status }}" @selected(($line['status'] ?? 'price') === $status)>{{ $statusLabel }}</option>
            @endforeach
        </select>
    </td>
    <td><button type="button" class="btn btn-ghost" data-quote-line-remove aria-label="Regel verwijderen"><i class="ph ph-trash"></i></button></td>
</tr>
