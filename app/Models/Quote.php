<?php

namespace App\Models;

use App\Support\QuotePrice;
use App\Support\RichText;
use App\Support\SiteUrl;
use App\Support\UnitConfiguration;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quote an admin composed in /account/offerte-samenstellen/ and sent to a client: a title, an accompanying text and
 * a composed unit, with the images of the unit that were made in the browser (3D views and the floor plan), and the rest
 * of what a full quote needs (see units.md): sale or rent, project details, a technical specification per section,
 * price lines, terms and open points.
 *
 * @property array<string, mixed> $configuration
 * @property array<string, string>|null $images Paths on the local disk by image key (front, back, plan).
 * @property array<string, string>|null $specifications Technical text per key of QuoteSpecification::DETAILS.
 * @property list<string>|null $hidden_specifications Keys of DETAILS that were unticked: kept, but not in the PDF.
 * @property list<array{description: string, quantity: int, price: int|null, period: string, status: string}>|null $lines
 *                                                                                                                        Price lines, prices in cents excluding VAT; see QuotePrice.
 * @property string|null $internal_notes Only for admins: never in the PDF or the mail.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
#[Fillable([
    'client_id', 'created_by', 'title', 'locale', 'kind', 'reference', 'intro', 'configuration', 'specifications', 'hidden_specifications', 'lines', 'terms', 'open_points', 'internal_notes', 'images', 'sent_at',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /** The images of the unit in the PDF, in their order: key => caption (translated with __()). */
    public const IMAGES = [
        'front' => 'Voorzijde en rechterzijde',
        'back' => 'Achterzijde en linkerzijde',
        'plan' => 'Plattegrond',
    ];

    /** Whether the unit is bought or rented: key => label (translated with __()). */
    public const KINDS = [
        'sale' => 'Koop',
        'rent' => 'Huur',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'specifications' => 'array',
            'hidden_specifications' => 'array',
            'lines' => 'array',
            'images' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function isRental(): bool
    {
        return $this->kind === 'rent';
    }

    /**
     * The prices worked out from the price lines.
     */
    public function price(): QuotePrice
    {
        return new QuotePrice($this->lines ?? []);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Full URL of the public unit composer opened with this quote's unit, in the language of the quote, so the client
     * can look at it in 3D. It is the same share link as "Kopieer link" in the composer.
     */
    public function composerUrl(): string
    {
        $code = UnitConfiguration::toShareCode(UnitConfiguration::normalize($this->configuration));

        return SiteUrl::absolute(SiteUrl::route('unit-composer.public', [UnitConfiguration::SHARE_KEY => $code], $this->locale));
    }

    /**
     * The accompanying text as HTML for the PDF and the mail. It comes from the rich text editor and is cleaned once
     * more; an older plain-text intro keeps its line breaks.
     */
    public function introHtml(): string
    {
        $intro = (string) $this->intro;

        if (RichText::isEmpty($intro)) {
            return '';
        }

        return preg_match('/<[a-z][^>]*>/i', $intro) ? RichText::clean($intro) : nl2br(e($intro));
    }

    /**
     * The quote number as shown on the PDF, e.g. "OF-2026-0007".
     */
    public function number(): string
    {
        return sprintf('OF-%s-%04d', ($this->created_at ?? now())->format('Y'), $this->id ?? 0);
    }
}
