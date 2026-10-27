<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Support\SiteUrl;
use Illuminate\Support\Str;

/**
 * Builds the /modulaire-units/ overview: a hero and one tile per unit type, combining the matching
 * subpages of /huren/ and /kopen/ (e.g. /huren/bouwunits/ and /kopen/bouwunits/ become "Bouwunits").
 */
class UnitTypesOverview
{
    public const PATH = 'modulaire-units';

    /**
     * Subpage slugs that differ between /huren/ and /kopen/ but describe the same unit type.
     *
     * @var array<string, string>
     */
    private const SLUG_ALIASES = ['kantine' => 'kantines'];

    public function __construct(private ShortcodeRenderer $shortcodes) {}

    public static function appliesTo(Page $page): bool
    {
        return $page->path === self::PATH;
    }

    /**
     * @return array{intro: string, content: string, image: ?string, types: list<array{name: string, image: ?string, rent: ?Page, buy: ?Page}>}
     */
    public function build(Page $page): array
    {
        $types = [];

        foreach (['huren' => 'rent', 'kopen' => 'buy'] as $section => $key) {
            $parent = Page::query()->published()->where('path', $section)->first();

            foreach ($parent?->children()->published()->get() ?? [] as $subpage) {
                $slug = self::SLUG_ALIASES[$subpage->slug] ?? $subpage->slug;

                $types[$slug] ??= ['name' => $this->typeName($subpage), 'image' => $subpage->tileImage(), 'rent' => null, 'buy' => null];
                $types[$slug][$key] = $subpage;
            }
        }

        uasort($types, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return [
            'intro' => $page->hero['subtitle'] ?? (string) $page->excerpt,
            'content' => trim($this->shortcodes->render((string) $page->content, $page)),
            'image' => SiteUrl::media($page->hero['image'] ?? $page->featured_image) ?? ($types === [] ? null : reset($types)['image']),
            'types' => array_values($types),
        ];
    }

    /**
     * "Kantoorunits huren" → "Kantoorunits".
     */
    private function typeName(Page $page): string
    {
        return Str::ucfirst(trim((string) preg_replace('/\s+(huren|kopen)$/i', '', $page->title)));
    }
}
