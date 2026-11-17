<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Content\ShortcodeRenderer;
use App\Support\Seo;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public unit composer: anyone can compose a unit in 3D without an account. It has no AI, so visitors
 * cannot run up costs on the Anthropic API key; the composition lives in the URL, so it can be shared.
 */
class UnitComposerController extends Controller
{
    /**
     * Path of the page in the admin whose content is shown as SEO text below the composer; its meta title and
     * meta description are used for the <head>. The route of the composer goes first, so the page itself is never shown.
     */
    public const PAGE_PATH = 'unit-samensteller';

    public function __invoke(Request $request): View
    {
        $page = Page::query()->published()->where('path', self::PAGE_PATH)->first();
        $page = $page?->hasTranslation() ? $page : null;

        $seo = Seo::route(__('Unit samenstellen'), __('Stel je eigen modulaire unit samen in 3D: kies de afmeting, het aantal units, de kleuren en waar ramen en deuren komen.'), 'unit-composer.public');

        if ($page?->meta_title) {
            $seo->title = $page->meta_title;
        }

        if ($description = Seo::description($page?->meta_description)) {
            $seo->description = $description;
        }

        return view('units.composer', [
            'configuration' => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'seoText' => filled($page?->content) ? app(ShortcodeRenderer::class)->render($page->content, $page) : null,
            'seo' => $seo,
        ]);
    }
}
