<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\UnitConfiguration;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test environment for composing units the way customers will: the configurator on its own page, not tied to
 * a unit on the website. The composition lives in the URL, so it can be bookmarked or shared.
 */
class UnitComposerController extends Controller
{
    public const QUERY_KEY = 'samenstelling';

    public function __invoke(Request $request): View
    {
        return view('admin.unit-composer', [
            'configuration' => $this->configurationFrom((string) $request->query(self::QUERY_KEY, '')),
        ]);
    }

    /**
     * Read a composition from its URL-safe base64 JSON form, or start from the default unit.
     *
     * @return array<string, mixed>
     */
    private function configurationFrom(string $encoded): array
    {
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $input = $json === false ? null : json_decode($json, true);

        return is_array($input) && filled($input['size'] ?? null)
            ? UnitConfiguration::normalize($input)
            : UnitConfiguration::defaults();
    }
}
