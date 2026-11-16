<?php

namespace App\Models;

use App\Support\UnitConfiguration;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quote an admin composed in /account/offerte-samenstellen/ and sent to a client: a title, an accompanying text and
 * a composed unit, with the images of the unit that were made in the browser (3D views and the floor plan).
 *
 * @property array<string, mixed> $configuration
 * @property array<string, string>|null $images Paths on the local disk by image key (front, back, plan).
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
#[Fillable(['client_id', 'created_by', 'title', 'intro', 'configuration', 'images', 'sent_at'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /** The images of the unit in the PDF, in their order: key => caption. */
    public const IMAGES = [
        'front' => 'Voorzijde en rechterzijde',
        'back' => 'Achterzijde en linkerzijde',
        'plan' => 'Plattegrond',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'images' => 'array',
            'sent_at' => 'datetime',
        ];
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
     * The quote number as shown on the PDF, e.g. "OF-2026-0007".
     */
    public function number(): string
    {
        return sprintf('OF-%s-%04d', ($this->created_at ?? now())->format('Y'), $this->id ?? 0);
    }
}
