<?php

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

/**
 * SEO audit (November 2026): takes the empty pages left over from WordPress offline, with a 301 to the page that
 * covers the subject, repairs internal links that point to a redirect or to a broken URL, and links pages with
 * content that no other page linked to from the footer. A page is only taken offline while it is still empty, so a
 * page that has since been written in the CMS stays online.
 */
return new class extends Migration
{
    /**
     * Empty page (path) => URL it redirects to; null when a redirect already exists.
     *
     * @var array<string, ?string>
     */
    private const GHOST_PAGES = [
        'aanbod' => null,
        'berichten' => '/blog/',
        'planning' => '/werkwijze/',
        'gebruikt/kantoor-units' => '/gebruikt/',
        'gebruikt/woonunits' => '/gebruikt/',
        'particulier' => '/woonunits/',
    ];

    /**
     * Link target in the content => the page it should point to.
     *
     * @var array<string, string>
     */
    private const LINKS = [
        'http://Waar kun je tweedehands kantoorunits kopen?' => '/waar-kun-je-tweedehands-kantoorunits-kopen/',
        '/aanbod/' => '/modulaire-units/',
    ];

    /**
     * Footer column (label) => [label => URL] of the pages added to it.
     *
     * @var array<string, array<string, string>>
     */
    private const FOOTER_LINKS = [
        'OPSLAG' => ['Tijdelijke opslag' => '/tijdelijke-opslag/'],
        'OVERIGE' => ['Werkwijze' => '/werkwijze/'],
    ];

    public function up(): void
    {
        foreach (self::GHOST_PAGES as $path => $target) {
            $page = Page::query()->where('path', $path)->where('status', 'published')->first();

            if ($page === null || ! $this->isEmpty($page)) {
                continue;
            }

            $page->update(['status' => 'draft']);

            if ($target !== null) {
                Redirect::query()->firstOrCreate(['from_path' => Redirect::normalize($path)], ['to_path' => $target, 'status_code' => 301]);
            }
        }

        $this->replaceLinks(self::LINKS);

        foreach (self::FOOTER_LINKS as $column => $links) {
            $parent = MenuItem::query()->where('menu', 'footer')->whereNull('parent_id')->where('label', $column)->first();

            foreach ($parent === null ? [] : $links as $label => $url) {
                if (! $parent->children()->where('url', $url)->exists()) {
                    MenuItem::query()->create([
                        'menu' => 'footer',
                        'parent_id' => $parent->id,
                        'label' => $label,
                        'url' => $url,
                        'sort' => (int) $parent->children()->max('sort') + 1,
                    ]);
                }
            }
        }
    }

    /**
     * Puts the pages back online and removes their redirects; the repaired links stay, they pointed to a broken URL
     * or a redirect.
     */
    public function down(): void
    {
        foreach (self::GHOST_PAGES as $path => $target) {
            Page::query()->where('path', $path)->where('status', 'draft')->update(['status' => 'published']);

            if ($target !== null) {
                Redirect::query()->where('from_path', Redirect::normalize($path))->where('to_path', $target)->delete();
            }
        }

        foreach (self::FOOTER_LINKS as $column => $links) {
            MenuItem::query()->where('menu', 'footer')->whereIn('url', array_values($links))
                ->whereHas('parent', fn ($query) => $query->where('label', $column))->delete();
        }
    }

    /**
     * Without text and without dynamic blocks (shortcodes) the page shows nothing but the header and footer.
     */
    private function isEmpty(Page $page): bool
    {
        $content = (string) $page->content;

        return ! str_contains($content, '[')
            && trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '';
    }

    /**
     * @param  array<string, string>  $links
     */
    private function replaceLinks(array $links): void
    {
        $search = array_map(fn (string $url): string => 'href="'.$url.'"', array_keys($links));
        $replace = array_map(fn (string $url): string => 'href="'.$url.'"', $links);

        foreach ([Page::class, Post::class] as $model) {
            $model::query()->where(function ($query) use ($search): void {
                foreach ($search as $href) {
                    $query->orWhere('content', 'like', '%'.$href.'%');
                }
            })->each(function (Model $item) use ($search, $replace): void {
                $item->update(['content' => str_replace($search, $replace, (string) $item->content)]);
            });
        }
    }
};
