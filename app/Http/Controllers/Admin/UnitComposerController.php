<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test environment for composing units the way customers do in their account, with the admin endpoints.
 */
class UnitComposerController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('portal.unit-composer', [
            'title' => 'Unit samenstellen (test)',
            'crumbs' => ['Beheer' => route('admin.dashboard'), 'Unit samenstellen' => null],
            'subtitle' => 'Testomgeving voor het samenstellen van units zoals klanten dat in hun account doen. Er wordt niets opgeslagen of op de website gezet: de samenstelling staat in de link.',
            'configuration' => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'previewUrl' => route('admin.units.preview'),
            'designUrl' => route('admin.units.design'),
        ]);
    }
}
