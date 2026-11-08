<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use App\Services\UnitConfigurationImages;
use App\Support\SiteUrl;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'translations', 'wp_id', 'user_id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'featured_image_alt', 'gallery', 'configuration', 'price_rent', 'price_buy', 'price_rent_week', 'price_buy_min', 'price_buy_max',
    'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'sort', 'published_at',
])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasTranslations, Publishable;

    /**
     * Attributes translated for the other languages of the website; the first one decides whether a record is translated.
     *
     * @var list<string>
     */
    public const TRANSLATABLE = ['title', 'slug', 'excerpt', 'content', 'featured_image_alt', 'price_rent', 'meta_title', 'meta_description'];

    /**
     * The statuses an admin picks from, with the label of the choice in the dropdown.
     * Only "published" is shown on the website; a draft is still being worked on, an offline unit was taken off the site.
     */
    public const STATUSES = ['published' => 'Publiceren', 'draft' => 'Concept', 'offline' => 'Offline'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'configuration' => 'array',
            'configuration_images' => 'array',
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The admin who composed the unit in the account's unit composer, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return SiteUrl::route('units.show', $this->slug, $this->urlLocale());
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
     * All images for the gallery: for a configured unit only the renders of the configurator; for a unit without
     * configuration (imported from WordPress) the featured image followed by the gallery.
     *
     * @return list<array{path: string, alt: string|null}>
     */
    public function images(): array
    {
        if (filled($this->configuration_images)) {
            return array_map(fn (string $path): array => ['path' => $path, 'alt' => null], $this->configuration_images);
        }

        $images = collect($this->gallery ?? [])
            ->map(fn (array|string $image): array => is_array($image) ? $image : ['path' => $image, 'alt' => null]);

        if ($this->featured_image && ! $images->contains('path', $this->featured_image)) {
            $images->prepend(['path' => $this->featured_image, 'alt' => $this->featured_image_alt]);
        }

        return $images->values()->all();
    }

    /**
     * The image for the unit overview: the first 3D photo of the configuration (the front view), otherwise the first
     * render, otherwise the featured image.
     */
    public function photo3d(): ?string
    {
        return collect($this->configuration_images ?? [])
            ->first(fn (string $path): bool => UnitConfigurationImages::usesPhotos([$path]))
            ?? ($this->configuration_images[0] ?? null)
            ?? ($this->featured_image ?: null);
    }

    /**
     * Everything the rotatable 3D viewer needs (on the detail page and in the /modulaire-units/ overview), or null for a unit without configuration.
     *
     * @return array{configuration: array<string, mixed>, dimensions: array{length: int, depth: int}, backdrops: array<string, array<string, string>>}|null
     */
    public function viewer(): ?array
    {
        if ($this->configuration === null) {
            return null;
        }

        $configuration = UnitConfiguration::normalize($this->configuration);

        return [
            'configuration' => $configuration,
            'dimensions' => UnitConfiguration::dimensions($configuration),
            'backdrops' => UnitRenderer::BACKDROPS,
        ];
    }

    /**
     * The rent price shown on the website: the price per week when it is filled in, otherwise the free-text indication
     * that units imported from WordPress still have.
     */
    public function rentPrice(): ?string
    {
        return filled($this->price_rent_week) ? __(':price per week', ['price' => self::euro($this->price_rent_week)]) : ($this->price_rent ?: null);
    }

    /**
     * The purchase price shown on the website: always on request.
     */
    public function buyPrice(): string
    {
        return __('Op aanvraag');
    }

    /**
     * A price as filled in by an admin, with the euro sign added when it is an amount without one:
     * "4500" becomes "€ 4.500", "75,-" becomes "€ 75,-"; "€ 75,-" and "Prijs op aanvraag" stay as they are.
     */
    public static function euro(string $price): string
    {
        $price = trim($price);

        if (! preg_match('/^\d/', $price)) {
            return $price;
        }

        if (ctype_digit($price)) {
            $price = number_format((int) $price, 0, ',', '.');
        }

        return "€ {$price}";
    }

    /**
     * A slug based on the title that is not used by another unit yet.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'unit';
        $slug = $base;
        $counter = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
