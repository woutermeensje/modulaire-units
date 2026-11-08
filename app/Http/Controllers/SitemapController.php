<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\UsedListing;
use App\Models\Vacancy;
use App\Support\Locale;
use App\Support\SiteUrl;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    /**
     * Pages that must never appear in the sitemap.
     *
     * @var list<string>
     */
    private const EXCLUDED_PATHS = ['mijn-offerte', 'mijn-account', 'winkelwagen'];

    public function index(): Response
    {
        /** @var Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}> $urls */
        $urls = collect();

        Page::query()->published()->where('noindex', false)->whereNotIn('path', self::EXCLUDED_PATHS)->get()
            ->each(fn (Page $page) => $urls->push(...$this->entries(fn (): string => $page->url(), $page->updated_at, $page)));

        Post::query()->published()->where('noindex', false)->get()
            ->each(fn (Post $post) => $urls->push(...$this->entries(fn (): string => $post->url(), $post->updated_at, $post)));

        $urls->push(...$this->entries(fn (): string => SiteUrl::route('units.index'), Unit::query()->max('updated_at')));
        $urls->push(...$this->entries(fn (): string => SiteUrl::route('unit-composer.public'), null));

        Unit::query()->published()->where('noindex', false)->get()
            ->each(fn (Unit $unit) => $urls->push(...$this->entries(fn (): string => $unit->url(), $unit->updated_at, $unit)));

        UnitTerm::query()->whereHas('units')->get()
            ->each(fn (UnitTerm $term) => $urls->push(...$this->entries(fn (): string => $term->url(), $term->updated_at, $term)));

        Product::query()->published()->where('noindex', false)->get()
            ->each(fn (Product $product) => $urls->push(...$this->entries(fn (): string => $product->url(), $product->updated_at, $product)));

        Vacancy::query()->published()->where('noindex', false)->get()
            ->each(fn (Vacancy $vacancy) => $urls->push(...$this->entries(fn (): string => $vacancy->url(), $vacancy->updated_at, $vacancy)));

        // Used listings are written by their providers in Dutch only, so they are only listed on the Dutch site.
        UsedListing::query()->published()->get()
            ->each(fn (UsedListing $listing) => $urls->push(...$this->entries(fn (): string => $listing->url(), $listing->updated_at, locales: [Locale::SOURCE])));

        $xml = view('sitemap', ['urls' => $urls->unique('loc')->values()])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin/', 'Disallow: /mijn-offerte/', 'Disallow: /zoeken/', 'Disallow: /en/my-quote/', 'Disallow: /en/search/', '', 'Sitemap: '.SiteUrl::absolute('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * One sitemap entry per language the page exists in, each listing all language versions as alternates (hreflang).
     *
     * @param  Closure(): string  $url
     * @param  list<string>|null  $locales
     * @return list<array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    private function entries(Closure $url, mixed $updatedAt, ?Model $record = null, ?array $locales = null): array
    {
        $alternates = [];

        foreach ($locales ?? Locale::all() as $locale) {
            if ($record !== null && method_exists($record, 'hasTranslation') && ! $record->hasTranslation($locale)) {
                continue;
            }

            $alternates[Locale::details($locale)['hreflang']] = SiteUrl::absolute(Locale::using($locale, $url));
        }

        $lastmod = $updatedAt ? Carbon::parse($updatedAt)->toAtomString() : null;

        return array_values(array_map(fn (string $loc): array => [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'alternates' => count($alternates) > 1 ? $alternates + ['x-default' => $alternates[Locale::SOURCE_DETAILS['hreflang']]] : [],
        ], $alternates));
    }
}
