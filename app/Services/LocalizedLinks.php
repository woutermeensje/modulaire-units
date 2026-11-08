<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\Vacancy;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Points internal links to Dutch pages at their translation: "/huren/kantoorunits/" becomes "/en/rent/office-units/"
 * on the English site. Content imported from WordPress, menus and fixed links in templates all link to Dutch URLs;
 * this keeps them working without storing a second set of links. Links to pages without translation stay Dutch.
 */
class LocalizedLinks
{
    /**
     * Content models with a URL per language.
     *
     * @var list<class-string<Model>>
     */
    private const MODELS = [Page::class, Post::class, Unit::class, Product::class, Vacancy::class, UnitTerm::class, Category::class, Author::class];

    /** @var array<string, array<string, string>> */
    private array $maps = [];

    /**
     * Rewrite the href of links and the action of forms in an HTML document. Links with a hreflang attribute (the
     * language switch) deliberately point to another language and are left alone.
     */
    public function localize(string $html, string $locale): string
    {
        return (string) preg_replace_callback('~<(?:a|form)\b[^>]*>~is', function (array $tag) use ($locale): string {
            if (preg_match('~\shreflang\s*=~i', $tag[0])) {
                return $tag[0];
            }

            return (string) preg_replace_callback(
                '~(\s(?:href|action)\s*=\s*)(["\'])(.*?)\2~is',
                fn (array $match): string => $match[1].$match[2].$this->localizeUrl($match[3], $locale).$match[2],
                $tag[0],
            );
        }, $html);
    }

    /**
     * The translated URL of an internal Dutch URL; any other URL is returned unchanged.
     */
    public function localizeUrl(string $url, string $locale): string
    {
        if (! preg_match('~^(?<origin>(?:https?:)?//[^/?#]+)?(?<path>/[^?#]*)(?<rest>[?#].*)?$~i', $url, $parts)) {
            return $url;
        }

        $origin = $parts['origin'] ?? '';

        if ($origin !== '' && ! $this->isOwnHost($origin)) {
            return $url;
        }

        $translated = $this->translatedPath($parts['path'], $locale);

        if ($translated === null) {
            return $url;
        }

        if (! str_ends_with($parts['path'], '/')) {
            $translated = rtrim($translated, '/');
        }

        return $origin.$translated.($parts['rest'] ?? '');
    }

    /**
     * The translated path of a Dutch path, or null when it has no translation (or already is translated).
     */
    public function translatedPath(string $path, string $locale): ?string
    {
        $normalized = self::normalize($path);

        if ($normalized === '/'.$locale.'/' || str_starts_with($normalized, '/'.$locale.'/')) {
            return null;
        }

        if (preg_match('~^/blog/page/(\d+)/$~', $normalized, $match)) {
            return '/'.$locale.'/blog/page/'.$match[1].'/';
        }

        return $this->map($locale)[$normalized] ?? null;
    }

    /**
     * Dutch path => translated path for every fixed route and every translated content record.
     *
     * @return array<string, string>
     */
    public function map(string $locale): array
    {
        return $this->maps[$locale] ??= $this->build($locale);
    }

    public static function normalize(string $path): string
    {
        $path = trim(rawurldecode($path), '/');

        return $path === '' ? '/' : '/'.$path.'/';
    }

    /**
     * @return array<string, string>
     */
    private function build(string $locale): array
    {
        $map = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $translatedName = $name === null ? null : Locale::routeName($name, $locale);

            if ($name === null || $route->parameterNames() !== [] || str_starts_with($name, $locale.'.') || ! Route::has($translatedName)) {
                continue;
            }

            $map[self::normalize($route->uri())] = self::normalize(Route::getRoutes()->getByName($translatedName)->uri());
        }

        foreach (self::MODELS as $model) {
            Locale::using($locale, fn () => $model::query()->translated()->get())
                ->each(function (Model $record) use (&$map, $locale): void {
                    $dutch = Locale::using(Locale::SOURCE, fn (): string => $record->url());
                    $map[self::normalize($dutch)] = Locale::using($locale, fn (): string => $record->url());
                });
        }

        return $map;
    }

    private function isOwnHost(string $origin): bool
    {
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $ownHosts = array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            app()->runningInConsole() ? null : strtolower(request()->getHost()),
        ]);

        return in_array($host, $ownHosts, true);
    }
}
