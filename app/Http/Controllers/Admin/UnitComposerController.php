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

    /**
     * What a customer starts from: an anthracite 6x3 with two windows in the long front wall and a door on the right.
     *
     * @var array<string, mixed>
     */
    public const STARTER_UNIT = [
        'size' => '6x3',
        'modules' => 1,
        'walls' => [
            'front' => ['empty', 'window', 'empty', 'empty', 'window', 'empty'],
            'right' => ['empty', 'door', 'empty'],
        ],
        'colors' => ['cladding' => '#383e42', 'frame' => '#383e42'],
        'background' => 'industrial',
    ];

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

        return UnitConfiguration::normalize(is_array($input) && filled($input['size'] ?? null) ? $input : self::STARTER_UNIT);
    }
}
