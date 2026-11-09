<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the /modulaire-units/ overview: the page content, followed by the published units that admins
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
     * @return array{content: string, units: Collection<int, Unit>}
     */
    public function build(Page $page): array
    {
        return [
            'content' => trim($this->shortcodes->render((string) $page->content, $page)),
            'units' => Unit::query()->published()->whereNotNull('user_id')->orderBy('sort')->oldest('published_at')->oldest('id')->get(),
        ];
    }
}
