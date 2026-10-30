<?php

namespace App\Http\Controllers\Admin;

use Anthropic\Core\Exceptions\APIException;
use App\Exceptions\UnitDesignFailed;
use App\Http\Controllers\Controller;
use App\Services\UnitDesigner;
use App\Support\UnitConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets Claude fill in or change the unit configurator from a description, in the admin and in the customer's unit composer.
 */
class UnitDesignController extends Controller
{
    public function __invoke(Request $request, UnitDesigner $designer): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ] + UnitConfiguration::rules('configuration'));

        if (! UnitDesigner::isConfigured()) {
            return response()->json(['message' => 'Er is nog geen ANTHROPIC_API_KEY ingesteld op de server.'], 503);
        }

        $current = filled($validated['configuration']['size'] ?? null) ? $validated['configuration'] : null;

        try {
            return response()->json($designer->design($validated['description'], $current));
        } catch (UnitDesignFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (APIException $exception) {
            report($exception);

            return response()->json(['message' => 'Claude is op dit moment niet bereikbaar. Probeer het over een paar minuten opnieuw.'], 502);
        }
    }
}
