<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Meta data for the <head> of a page: title, description, canonical, robots and Open Graph.
 */
class Seo
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,
        public bool $noindex = false,
        public string $type = 'website',
    ) {}

    public static function make(string $title, ?string $description = null, ?string $canonical = null): self
    {
        return new self(static::withSiteName($title), $description, $canonical);
    }

    /**
     * Build meta data from a content model that has the shared SEO columns.
     */
    public static function for(Model $model, ?string $fallbackDescription = null): self
    {
        $title = $model->meta_title ?: static::withSiteName((string) ($model->title ?? $model->name ?? ''));
        $description = $model->meta_description ?: $fallbackDescription;

        return new self(
            title: $title,
            description: $description ? Str::limit(trim(strip_tags(html_entity_decode($description))), 300) : null,
            canonical: method_exists($model, 'url') ? $model->url() : null,
            image: $model->og_image ?: ($model->featured_image ?? null),
            noindex: (bool) ($model->noindex ?? false),
            type: $model instanceof Post ? 'article' : 'website',
        );
    }

    public static function withSiteName(string $title): string
    {
        $siteName = Setting::get('site_name');

        return $title === '' || $title === $siteName ? (string) $siteName : "{$title} - {$siteName}";
    }

    public function canonicalUrl(): ?string
    {
        if (! $this->canonical) {
            return null;
        }

        return SiteUrl::absolute(str_starts_with($this->canonical, 'http') ? $this->canonical : SiteUrl::path($this->canonical));
    }

    /**
     * Social networks don't render SVG, so a rendered unit configuration is never used as share image.
     */
    public function imageUrl(): ?string
    {
        if (str_ends_with(strtolower((string) $this->image), '.svg')) {
            return null;
        }

        $image = SiteUrl::media($this->image);

        return $image ? SiteUrl::absolute($image) : null;
    }
}
