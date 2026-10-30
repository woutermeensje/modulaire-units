<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders the live 2D preview of the unit configurator, in the admin and in the customer's unit composer.
 */
class UnitPreviewController extends Controller
{
    public function __invoke(Request $request, UnitRenderer $renderer): Response
    {
        $validated = $request->validate(UnitConfiguration::rules('configuration'));

        return response($renderer->render($validated['configuration'] ?? []), 200, ['Content-Type' => 'image/svg+xml']);
    }
}
