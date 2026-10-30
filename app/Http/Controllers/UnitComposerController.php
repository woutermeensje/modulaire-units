<?php

namespace App\Http\Controllers;

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
    public function __invoke(Request $request): View
    {
        return view('units.composer', [
            'configuration' => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'seo' => Seo::make('Unit samenstellen', 'Stel je eigen modulaire unit samen in 3D: kies de afmeting, het aantal units, de kleuren en waar ramen en deuren komen.', route('unit-composer.public', [], false)),
        ]);
    }
}
