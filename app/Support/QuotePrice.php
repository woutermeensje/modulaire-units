<?php

namespace App\Support;

/**
 * The prices of a quote, worked out from its price lines. Nothing is stored twice: totals are always calculated here.
 *
 * Only lines with the status "price" count towards the total. "Included" lines have no amount of their own, optional
 * lines are offered on top, "client" lines are left to the client and "tbd" lines still have to be worked out.
 * Amounts are in cents, excluding VAT.
 *
 * @phpstan-type Line array{description: string, quantity: int, price: int|null, period: string, status: string}
 */
final class QuotePrice
{
    public const VAT_RATE = 21;

    /** What a line's amount is for: label in the PDF (translated with __()). */
    public const PERIODS = [
        'once' => 'eenmalig',
        'week' => 'per week',
    ];

    /** What a line is in the quote: label in the admin and the PDF (translated with __()). */
    public const STATUSES = [
        'price' => 'Berekend',
        'included' => 'Inbegrepen',
        'optional' => 'Optioneel',
        'client' => 'Door opdrachtgever',
        'tbd' => 'Nog te bepalen',
    ];

    /**
     * @param  list<Line>  $lines
     */
    public function __construct(private readonly array $lines, private readonly ?int $rentalWeeks = null) {}

    /**
     * The lines with one of the given statuses, in their order.
     *
     * @return list<Line>
     */
    public function lines(string ...$statuses): array
    {
        return array_values(array_filter($this->lines, fn (array $line): bool => in_array($line['status'], $statuses, true)));
    }

    /**
     * @param  Line  $line
     */
    public static function lineTotal(array $line): int
    {
        return $line['quantity'] * ($line['price'] ?? 0);
    }

    /**
     * The one-off costs: the unit when it is bought, transport, placing and so on.
     */
    public function oneOff(): int
    {
        return $this->sum('once');
    }

    /**
     * The rent per week.
     */
    public function weekly(): int
    {
        return $this->sum('week');
    }

    /**
     * The rent over the whole rental period, or null when the period isn't known yet.
     */
    public function rental(): ?int
    {
        return $this->rentalWeeks ? $this->weekly() * $this->rentalWeeks : null;
    }

    public function rentalWeeks(): ?int
    {
        return $this->rentalWeeks;
    }

    /**
     * The total excluding VAT: one-off costs plus the rent over the rental period, when that period is known.
     */
    public function totalExcludingVat(): int
    {
        return $this->oneOff() + ($this->rental() ?? 0);
    }

    public function vat(): int
    {
        return (int) round($this->totalExcludingVat() * self::VAT_RATE / 100);
    }

    public function totalIncludingVat(): int
    {
        return $this->totalExcludingVat() + $this->vat();
    }

    /**
     * Whether there is anything to show in the prices of the PDF.
     */
    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * An amount in cents as "€ 1.234,50" (Dutch) or "€ 1,234.50" (English), in the active language.
     */
    public static function format(int $cents): string
    {
        $dutch = app()->getLocale() === 'nl';

        return '€ '.number_format($cents / 100, 2, $dutch ? ',' : '.', $dutch ? '.' : ',');
    }

    private function sum(string $period): int
    {
        return array_sum(array_map(
            fn (array $line): int => self::lineTotal($line),
            array_filter($this->lines('price'), fn (array $line): bool => $line['period'] === $period),
        ));
    }
}
