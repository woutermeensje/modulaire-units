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
        return new self(static::withSiteName($title), static::description($description), $canonical);
    }

    /**
     * Google shows roughly this many characters of a meta description.
     */
    public const DESCRIPTION_LENGTH = 160;

    /**
     * Build meta data from a content model that has the shared SEO columns; without a meta description or
     * fallback the description is taken from the content, so every page gets one.
     */
    public static function for(Model $model, ?string $fallbackDescription = null): self
    {
        $title = $model->meta_title ?: static::withSiteName((string) ($model->title ?? $model->name ?? ''));
        $description = static::description($model->meta_description)
            ?? static::description($fallbackDescription)
            ?? static::description($model->content ?? null);

        return new self(
            title: $title,
            description: $description,
            canonical: method_exists($model, 'url') ? $model->url() : null,
            image: $model->og_image ?: ($model->featured_image ?? null),
            noindex: (bool) ($model->noindex ?? false),
            type: $model instanceof Post ? 'article' : 'website',
        );
    }

    /**
     * Plain text of at most DESCRIPTION_LENGTH characters, without HTML or shortcodes; null when nothing is left.
     */
    public static function description(?string $text): ?string
    {
        $plain = preg_replace(['/\[[^\]]*\]/', '/<(script|style)\b.*?<\/\1>/is'], ' ', (string) $text);
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $plain)), ENT_QUOTES | ENT_HTML5)));

        return $plain === '' ? null : Str::limit($plain, self::DESCRIPTION_LENGTH, '…', preserveWords: true);
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
