<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets customers compose their own unit in 3D. The composition lives in the URL, so it can be bookmarked or shared.
 */
class UnitComposerController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('portal.unit-composer', [
            'title' => 'Unit samenstellen',
            'crumbs' => ['Account' => route('dashboard'), 'Unit samenstellen' => null],
            'subtitle' => 'Stel je eigen unit samen: kies de afmeting, het aantal units, de kleuren en waar ramen en deuren komen. Bewaar of deel je samenstelling met de link.',
            'configuration' => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'previewUrl' => route('unit-composer.preview'),
            'designUrl' => route('unit-composer.design'),
            'quoteUrl' => route('unit-composer.quote'),
        ]);
    }
}
