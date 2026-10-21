<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'wp_id', 'parent_id', 'title', 'slug', 'path', 'template', 'show_title', 'hero', 'content', 'custom_css',
    'excerpt', 'featured_image', 'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'menu_order', 'published_at',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, Publishable;

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
        return SiteUrl::path($this->path);
    }

    /**
     * Tile image for unit overviews: a fixed photo in public/marketing/units/{slug}.jpg, else the featured image.
     */
    public function tileImage(): ?string
    {
        $marketingImage = 'marketing/units/'.$this->slug.'.jpg';

        if ($this->slug !== '' && is_file(public_path($marketingImage))) {
            return '/'.$marketingImage;
        }

        return SiteUrl::media(Media::variant($this->featured_image, 'large'));
    }

    public function isHome(): bool
    {
        return $this->path === '';
    }

    /**
     * Rebuild the full path from the parent chain, e.g. "kopen/bouwunits".
     */
    public function buildPath(): string
    {
        if ($this->slug === '' || $this->slug === null) {
            return '';
        }

        $parentPath = $this->parent?->path;

        return trim(($parentPath ? $parentPath.'/' : '').$this->slug, '/');
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->slug = (string) $page->slug;
            $page->path = $page->buildPath();
        });

        static::saved(function (Page $page): void {
            if ($page->wasChanged('path')) {
                $page->children->each->save();
            }
        });
    }
}
