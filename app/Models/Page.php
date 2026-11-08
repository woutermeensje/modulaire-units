<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use App\Support\Locale;
use App\Support\SiteUrl;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'translations', 'wp_id', 'parent_id', 'title', 'slug', 'path', 'template', 'show_title', 'hero', 'content', 'custom_css',
    'excerpt', 'featured_image', 'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'menu_order', 'published_at',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasTranslations, Publishable;

    /**
     * Attributes translated for the other languages of the website; the first one decides whether a record is translated.
     *
     * @var list<string>
     */
    public const TRANSLATABLE = ['title', 'slug', 'path', 'hero', 'content', 'excerpt', 'meta_title', 'meta_description'];

    public const TEMPLATES = [
        'default' => 'Standaard',
        'landing' => 'Landingspagina (met hero)',
        'service' => 'Dienstpagina (hero met foto, afwisselende blokken)',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hero' => 'array',
            'show_title' => 'boolean',
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('menu_order')->orderBy('title');
    }

    public function url(): string
    {
        return SiteUrl::path($this->path, $this->urlLocale());
    }

    /**
     * Tile image for unit overviews: a fixed photo in public/marketing/units/{slug}.jpg, else the featured image.
     */
    public function tileImage(): ?string
    {
        $marketingImage = 'marketing/units/'.$this->source('slug').'.jpg';

        if ($this->source('slug') !== '' && is_file(public_path($marketingImage))) {
            return '/'.$marketingImage;
        }

        return SiteUrl::media(Media::variant($this->featured_image, 'large'));
    }

    public function isHome(): bool
    {
        return $this->source('path') === '';
    }

    /**
     * Rebuild the full path from the parent chain, e.g. "kopen/bouwunits".
     */
    public function buildPath(): string
    {
        $slug = (string) ($this->getAttributes()['slug'] ?? '');

        if ($slug === '') {
            return '';
        }

        $parentPath = $this->parent?->getAttributes()['path'] ?? null;

        return trim(($parentPath ? $parentPath.'/' : '').$slug, '/');
    }

    /**
     * Rebuild the path in a translated language from the translated slugs of the parent chain, e.g. "buy/site-cabins";
     * a parent without translation contributes its Dutch path. Null while the slug is not translated.
     */
    public function buildTranslatedPath(string $locale): ?string
    {
        if ($this->buildPath() === '') {
            return '';
        }

        $slug = $this->translation($locale, 'slug');

        if (blank($slug)) {
            return null;
        }

        $parentPath = $this->parent ? ($this->parent->translation($locale, 'path') ?? $this->parent->getAttributes()['path']) : null;

        return trim(($parentPath ? $parentPath.'/' : '').$slug, '/');
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->slug = (string) ($page->getAttributes()['slug'] ?? '');
            $page->path = $page->buildPath();

            foreach (array_keys(Locale::TRANSLATIONS) as $locale) {
                if ($page->hasTranslation($locale)) {
                    $page->setTranslations($locale, ['path' => $page->buildTranslatedPath($locale)]);
                }
            }
        });

        static::saved(function (Page $page): void {
            if ($page->wasChanged(['path', 'translations'])) {
                $page->children->each->save();
            }
        });
    }
}
