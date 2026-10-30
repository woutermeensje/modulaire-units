<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Support\SiteUrl;

/**
 * Builds the /modulaire-units/ overview: a hero and the page content, followed by the public unit composer.
 */
class UnitTypesOverview
{
    public const PATH = 'modulaire-units';

    public function __construct(private ShortcodeRenderer $shortcodes) {}

    public static function appliesTo(Page $page): bool
    {
        return $page->path === self::PATH;
    }

    /**
     * @return array{intro: string, content: string, image: ?string}
     */
    public function build(Page $page): array
    {
        return [
            'intro' => $page->hero['subtitle'] ?? (string) $page->excerpt,
            'content' => trim($this->shortcodes->render((string) $page->content, $page)),
            'image' => SiteUrl::media($page->hero['image'] ?? $page->featured_image),
        ];
    }
}
