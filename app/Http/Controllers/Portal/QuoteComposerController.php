<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The unit composer for making quotes, for admins only. It is the customer's composer plus everything that was taken
 * out of it to keep it simple: the use of the unit, wall sockets and network points, furniture and a step in front of
 * the doors. The composition is added to "Mijn offerte", where these items are listed with the unit.
 */
class QuoteComposerController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('portal.unit-composer', [
            'title' => 'Offerte samenstellen',
            'crumbs' => ['Account' => route('dashboard'), 'Offerte samenstellen' => null],
            'subtitle' => 'Stel een unit samen voor een offerte, met alles wat klanten zelf niet kiezen: type gebruik, elektra en netwerk, meubilair en een opstapje. Voeg hem toe aan de offerte; de gekozen items staan daar opgesomd bij de unit.',
            'configuration' => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'previewUrl' => route('unit-composer.preview'),
            'quoteUrl' => route('unit-composer.quote'),
            'quoteExtras' => true,
        ]);
    }
}
