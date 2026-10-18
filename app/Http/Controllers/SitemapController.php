<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\Vacancy;
use App\Support\SiteUrl;
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
        /** @var Collection<int, array{loc: string, lastmod: ?string}> $urls */
        $urls = collect();

        Page::query()->published()->where('noindex', false)->whereNotIn('path', self::EXCLUDED_PATHS)->get()
            ->each(fn (Page $page) => $urls->push($this->entry($page->url(), $page->updated_at)));

        Post::query()->published()->where('noindex', false)->get()
            ->each(fn (Post $post) => $urls->push($this->entry($post->url(), $post->updated_at)));

        $urls->push($this->entry(SiteUrl::path(route('units.index', [], false)), Unit::query()->max('updated_at')));
        Unit::query()->published()->where('noindex', false)->get()
            ->each(fn (Unit $unit) => $urls->push($this->entry($unit->url(), $unit->updated_at)));

        UnitTerm::query()->whereHas('units')->get()
            ->each(fn (UnitTerm $term) => $urls->push($this->entry($term->url(), $term->updated_at)));

        Product::query()->published()->where('noindex', false)->get()
            ->each(fn (Product $product) => $urls->push($this->entry($product->url(), $product->updated_at)));

        Vacancy::query()->published()->where('noindex', false)->get()
            ->each(fn (Vacancy $vacancy) => $urls->push($this->entry($vacancy->url(), $vacancy->updated_at)));

        $xml = view('sitemap', ['urls' => $urls->unique('loc')->values()])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin/', 'Disallow: /mijn-offerte/', 'Disallow: /zoeken/', '', 'Sitemap: '.SiteUrl::absolute('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return array{loc: string, lastmod: ?string}
     */
    private function entry(string $url, mixed $updatedAt): array
    {
        return [
            'loc' => SiteUrl::absolute($url),
            'lastmod' => $updatedAt ? Carbon::parse($updatedAt)->toAtomString() : null,
        ];
    }
}
