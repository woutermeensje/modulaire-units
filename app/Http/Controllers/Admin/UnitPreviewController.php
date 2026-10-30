<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\FloorPlanRenderer;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders the live floor plan of the unit configurator, in the admin, the customer's and the public unit composer.
 */
class UnitPreviewController extends Controller
{
    public function __invoke(Request $request, FloorPlanRenderer $renderer): Response
    {
        $validated = $request->validate(UnitConfiguration::rules('configuration'));

        return response($renderer->render($validated['configuration'] ?? []), 200, ['Content-Type' => 'image/svg+xml']);
    }
}
