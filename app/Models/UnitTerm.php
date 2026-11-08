<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Locale;
use App\Support\SiteUrl;
use Database\Factories\UnitTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['translations', 'wp_id', 'taxonomy', 'name', 'slug', 'description', 'sort'])]
class UnitTerm extends Model
{
    /** @use HasFactory<UnitTermFactory> */
    use HasFactory, HasTranslations;

    /**
     * Attributes translated for the other languages of the website; the first one decides whether a record is translated.
     *
     * @var list<string>
     */
    public const TRANSLATABLE = ['name', 'slug', 'description'];

    public const TYPE = 'type';

    public const CONDITION = 'conditie';

    public const OFFER = 'aanbod';

    /**
     * Taxonomy key => [label, URL prefix of the original WordPress archive, URL prefix per translated language].
     *
     * @var array<string, array{label: string, prefix: string, prefixes: array<string, string>}>
     */
    public const TAXONOMIES = [
        self::TYPE => ['label' => 'Type unit', 'prefix' => 'unit-type', 'prefixes' => ['en' => 'unit-type']],
        self::CONDITION => ['label' => 'Conditie', 'prefix' => 'conditie', 'prefixes' => ['en' => 'condition']],
        self::OFFER => ['label' => 'Aanbod', 'prefix' => 'aanbod', 'prefixes' => ['en' => 'offer']],
    ];

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    #[Scope]
    protected function inTaxonomy(Builder $query, string $taxonomy): void
    {
        $query->where('taxonomy', $taxonomy);
    }

    public function url(): string
    {
        $locale = $this->urlLocale();

        return SiteUrl::path(self::prefix($this->taxonomy, $locale).'/'.$this->slug, $locale);
    }

    /**
     * The URL prefix of a taxonomy archive in a language, e.g. "conditie" in Dutch and "condition" in English.
     */
    public static function prefix(string $taxonomy, ?string $locale = null): string
    {
        $locale ??= Locale::current();

        return self::TAXONOMIES[$taxonomy]['prefixes'][$locale] ?? self::TAXONOMIES[$taxonomy]['prefix'];
    }

    /**
     * @return list<string>
     */
    public static function prefixes(?string $locale = null): array
    {
        return array_map(fn (string $taxonomy): string => self::prefix($taxonomy, $locale), array_keys(self::TAXONOMIES));
    }

    public static function taxonomyForPrefix(string $prefix): ?string
    {
        foreach (array_keys(self::TAXONOMIES) as $taxonomy) {
            if (self::prefix($taxonomy) === $prefix) {
                return $taxonomy;
            }
        }

        return null;
    }
}
