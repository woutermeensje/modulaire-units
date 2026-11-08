<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Setting;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Meta data for the <head> of a page: title, description, canonical, robots and Open Graph.
 */
class Seo
{
    /**
     * @param  array<string, string>  $alternates  The URL of this page per language (locale => root-relative URL), for hreflang and the language switch.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,
        public bool $noindex = false,
        public string $type = 'website',
        public array $alternates = [],
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

        $seo = new self(
            title: $title,
            description: $description,
            canonical: method_exists($model, 'url') ? $model->url() : null,
            image: $model->og_image ?: ($model->featured_image ?? null),
            noindex: (bool) ($model->noindex ?? false),
            type: $model instanceof Post ? 'article' : 'website',
        );

        return method_exists($model, 'url') ? $seo->withAlternates(fn (): string => $model->url(), $model) : $seo;
    }

    /**
     * Meta data for a fixed route such as "units.index", with its URL in every language.
     *
     * @param  array<string, mixed>|string|int  $parameters
     */
    public static function route(string $title, ?string $description, string $name, array|string|int $parameters = []): self
    {
        return static::make($title, $description, SiteUrl::route($name, $parameters))
            ->withAlternates(fn (): string => SiteUrl::route($name, $parameters));
    }

    /**
     * Collect the URL of the page in every language by running the callback per language. With a record, languages
     * in which it has no translation are left out, because the page doesn't exist there.
     *
     * @param  Closure(): string  $url
     */
    public function withAlternates(Closure $url, ?Model $record = null): self
    {
        $this->alternates = [];

        foreach (Locale::all() as $locale) {
            if ($record !== null && method_exists($record, 'hasTranslation') && ! $record->hasTranslation($locale)) {
                continue;
            }

            $this->alternates[$locale] = Locale::using($locale, $url);
        }

        return $this;
    }

    /**
     * Absolute alternate URLs for hreflang; empty when the page exists in one language only.
     *
     * @return array<string, string>
     */
    public function alternateUrls(): array
    {
        if (count($this->alternates) < 2) {
            return [];
        }

        return array_map(fn (string $url): string => SiteUrl::absolute($url), $this->alternates);
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

        return SiteUrl::absolute(str_starts_with($this->canonical, 'http') ? $this->canonical : SiteUrl::path($this->canonical, Locale::SOURCE));
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
