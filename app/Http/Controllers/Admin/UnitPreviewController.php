<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\FloorPlanRenderer;
use App\Support\Locale;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Renders the live floor plan of the unit configurator, in the admin, the customer's and the public unit composer.
 * The quote composer asks for it in the language of the quote (locale), for the floor plan in the PDF.
 */
class UnitPreviewController extends Controller
{
    public function __invoke(Request $request, FloorPlanRenderer $renderer): Response
    {
        $validated = $request->validate([
            ...UnitConfiguration::rules('configuration'),
            'locale' => ['nullable', Rule::in(Locale::all())],
        ]);
        $svg = Locale::using($validated['locale'] ?? Locale::current(), fn (): string => $renderer->render($validated['configuration'] ?? []));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
