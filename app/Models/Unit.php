<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'wp_id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'featured_image_alt', 'gallery', 'price_rent', 'price_buy',
    'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'sort', 'published_at',
])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, Publishable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<UnitTerm, $this>
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(UnitTerm::class)->orderBy('sort')->orderBy('name');
    }

    /**
     * @return Collection<int, UnitTerm>
     */
    public function termsFor(string $taxonomy): Collection
    {
        return $this->terms->where('taxonomy', $taxonomy)->values();
    }

    public function url(): string
    {
        return SiteUrl::path(route('units.show', $this->slug, false));
    }

    /**
     * Label for the badge on unit cards, e.g. "Koop & Huur".
     */
    public function offerLabel(): ?string
    {
        $offers = $this->termsFor(UnitTerm::OFFER);

        return $offers->isEmpty() ? null : $offers->pluck('name')->implode(' & ');
    }

    public function summary(int $words = 6): string
    {
        $text = $this->excerpt ?: strip_tags((string) $this->content);

        return Str::words(trim(preg_replace('/\s+/', ' ', html_entity_decode($text))), $words, '…');
    }

    /**
     * All images for the gallery, featured image first.
     *
     * @return list<array{path: string, alt: string|null}>
     */
    public function images(): array
    {
        $images = collect($this->gallery ?? [])
            ->map(fn (array|string $image): array => is_array($image) ? $image : ['path' => $image, 'alt' => null]);

        if ($this->featured_image && ! $images->contains('path', $this->featured_image)) {
            $images->prepend(['path' => $this->featured_image, 'alt' => $this->featured_image_alt]);
        }

        return $images->values()->all();
    }
}
