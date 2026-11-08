<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Models\Unit;
use App\Support\SiteUrl;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the /modulaire-units/ overview: a hero and the page content, followed by the published units that admins
 * composed in their account (/account/unit-samenstellen/).
 */
class UnitTypesOverview
{
    public const PATH = 'modulaire-units';

    public function __construct(private ShortcodeRenderer $shortcodes) {}

    public static function appliesTo(Page $page): bool
    {
        return $page->source('path') === self::PATH;
    }

    /**
     * @return array{intro: string, content: string, image: ?string, units: Collection<int, Unit>}
     */
    public function build(Page $page): array
    {
        return [
            'intro' => $page->hero['subtitle'] ?? (string) $page->excerpt,
            'content' => trim($this->shortcodes->render((string) $page->content, $page)),
            'image' => SiteUrl::media($page->hero['image'] ?? $page->featured_image),
            'units' => Unit::query()->published()->whereNotNull('user_id')->orderBy('sort')->latest('published_at')->get(),
        ];
    }
}
