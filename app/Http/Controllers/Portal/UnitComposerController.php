<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets customers compose their own unit in 3D. The composition lives in the URL, so it can be bookmarked or shared.
 *
 * Admins use the same composer to make units for the landing pages: instead of a quote and a share link they get
 * a title, a description and a button that adds the unit to the products.
 */
class UnitComposerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();
        $previous = $request->old('configuration');

        return view('portal.unit-composer', [
            'title' => 'Unit samenstellen',
            'crumbs' => ['Account' => route('dashboard'), 'Unit samenstellen' => null],
            'subtitle' => $isAdmin
                ? 'Stel een unit samen, geef hem een titel en beschrijving en voeg hem toe aan de producten. De unit komt offline in het beheer te staan, zodat je hem daar kunt aanvullen en publiceren.'
                : 'Stel je eigen unit samen: kies de afmeting, het aantal units, de kleuren en waar ramen en deuren komen. Bewaar of deel je samenstelling met de link.',
            'configuration' => is_array($previous) && filled($previous['size'] ?? null)
                ? UnitConfiguration::normalize($previous)
                : UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'previewUrl' => route('unit-composer.preview'),
            'quoteUrl' => $isAdmin ? null : route('unit-composer.quote'),
            'storeUrl' => $isAdmin ? route('unit-composer.store') : null,
        ]);
    }
}
