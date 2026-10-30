<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * The layout of a configurable modular unit: a fixed footprint, walls divided into 1 m slots
 * that each hold nothing, a window or a door, and a handful of colours.
 *
 * Walls are named as seen from outside the unit; slots run from left to right as seen from there.
 * The front and back walls are the long sides, left and right are the short sides.
 *
 * Several units can be coupled long side to long side into one open space: the front wall belongs to the
 * first unit, the back wall to the last one, and the left and right walls run along all of them.
 *
 * A second layer of the same units can be stacked on top. It has walls of its own (upper_walls), so it can have
 * other windows and doors than the ground floor; without a second layer upper_walls is left out. With stairs,
 * every door on the second layer gets an outside staircase with a landing.
 *
 * @phpstan-type Configuration array{
 *     size: string,
 *     modules: int,
 *     floors: int,
 *     walls: array{front: list<string>, back: list<string>, left: list<string>, right: list<string>},
 *     upper_walls?: array{front: list<string>, back: list<string>, left: list<string>, right: list<string>},
 *     stairs: bool,
 *     colors: array{cladding: string, frame: string},
 *     background: string,
 * }
 */
class UnitConfiguration
{
    /** @var array<string, array{label: string, length: int, depth: int}> */
    public const SIZES = [
        '3x3' => ['label' => '3 x 3 meter', 'length' => 3, 'depth' => 3],
        '6x3' => ['label' => '6 x 3 meter', 'length' => 6, 'depth' => 3],
    ];

    /** @var array<string, string> */
    public const WALLS = [
        'front' => 'Voorzijde',
        'right' => 'Rechterzijde',
        'back' => 'Achterzijde',
        'left' => 'Linkerzijde',
    ];

    public const MAX_MODULES = 4;

    public const MAX_FLOORS = 2;

    /**
     * The sets of walls per layer, from the ground up: configuration key => name.
     *
     * @var array<string, string>
     */
    public const LEVELS = [
        'walls' => 'Begane grond',
        'upper_walls' => 'Verdieping',
    ];

    public const EMPTY = 'empty';

    public const WINDOW = 'window';

    public const DOOR = 'door';

    /** @var array<string, string> */
    public const SLOTS = [
        self::EMPTY => 'Dicht',
        self::WINDOW => 'Raam',
        self::DOOR => 'Deur',
    ];

    /** @var array<string, string> */
    public const BACKGROUNDS = [
        'industrial' => 'Industrieterrein',
        'meadow' => 'Grasveld',
        'site' => 'Bouwterrein',
        'plain' => 'Neutraal',
    ];

    /**
     * The colours the walls and the frame can have: hex code => name (white RAL 9010, anthracite RAL 7016, blue RAL 5010).
     *
     * @var array<string, string>
     */
    public const COLORS = [
        '#f2f0e8' => 'Wit',
        '#383e42' => 'Antraciet',
        '#0e4c92' => 'Blauw',
    ];

    /** @var array{cladding: string, frame: string} */
    public const DEFAULT_COLORS = ['cladding' => '#f2f0e8', 'frame' => '#383e42'];

    public const COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

    /** Query parameter that holds a composition in a share link. */
    public const SHARE_KEY = 'samenstelling';

    /**
     * What a customer starts composing from: an anthracite 6x3 with two windows in the long front wall and a door on the right.
     *
     * @var array<string, mixed>
     */
    public const STARTER = [
        'size' => '6x3',
        'modules' => 1,
        'walls' => [
            'front' => ['empty', 'window', 'empty', 'empty', 'window', 'empty'],
            'right' => ['empty', 'door', 'empty'],
        ],
        'colors' => ['cladding' => '#383e42', 'frame' => '#383e42'],
        'background' => 'industrial',
    ];

    /**
     * Validation rules for a configuration submitted under the given input key.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $key): array
    {
        return [
            $key => ['nullable', 'array'],
            "{$key}.size" => ['nullable', Rule::in(array_keys(self::SIZES))],
            "{$key}.modules" => ['nullable', 'integer', 'between:1,'.self::MAX_MODULES],
            "{$key}.floors" => ['nullable', 'integer', 'between:1,'.self::MAX_FLOORS],
            "{$key}.walls" => ['array'],
            "{$key}.walls.*" => ['array'],
            "{$key}.walls.*.*" => [Rule::in(array_keys(self::SLOTS))],
            "{$key}.upper_walls" => ['array'],
            "{$key}.upper_walls.*" => ['array'],
            "{$key}.upper_walls.*.*" => [Rule::in(array_keys(self::SLOTS))],
            "{$key}.stairs" => ['nullable', 'boolean'],
            "{$key}.colors" => ['array'],
            "{$key}.colors.*" => ['nullable', 'string', Rule::in(array_keys(self::COLORS))],
            "{$key}.background" => ['nullable', Rule::in(array_keys(self::BACKGROUNDS))],
        ];
    }

    /**
     * Number of 1 m slots on a wall. The left and right walls run along every coupled unit.
     */
    public static function slotCount(string $size, string $wall, int $modules = 1): int
    {
        $dimensions = self::SIZES[$size] ?? self::SIZES['3x3'];

        return in_array($wall, ['front', 'back'], true) ? $dimensions['length'] : $dimensions['depth'] * $modules;
    }

    /**
     * Outside dimensions in metres of all coupled units together.
     *
     * @param  Configuration  $configuration
     * @return array{length: int, depth: int, floors: int}
     */
    public static function dimensions(array $configuration): array
    {
        $size = self::SIZES[$configuration['size']];

        return ['length' => $size['length'], 'depth' => $size['depth'] * $configuration['modules'], 'floors' => $configuration['floors']];
    }

    /**
     * A description such as "Modulaire unit van 6 x 3 meter", "2 gekoppelde units van 6 x 3 meter (6 x 6 meter)" or
     * "8 units van 6 x 3 meter in 2 lagen (6 x 12 meter)".
     *
     * @param  Configuration  $configuration
     */
    public static function label(array $configuration): string
    {
        $size = self::SIZES[$configuration['size']]['label'];

        $dimensions = self::dimensions($configuration);
        $footprint = "{$dimensions['length']} x {$dimensions['depth']} meter";

        if ($configuration['floors'] > 1) {
            $units = $configuration['modules'] * $configuration['floors'];

            return "{$units} units van {$size} in {$configuration['floors']} lagen ({$footprint})";
        }

        if ($configuration['modules'] === 1) {
            return 'Modulaire unit van '.$size;
        }

        return "{$configuration['modules']} gekoppelde units van {$size} ({$footprint})";
    }

    /**
     * A closed unit with a door in the middle of the front wall.
     *
     * @return Configuration
     */
    public static function defaults(string $size = '6x3'): array
    {
        $configuration = self::normalize(['size' => $size]);
        $configuration['walls']['front'][intdiv(self::slotCount($size, 'front'), 2)] = self::DOOR;

        return $configuration;
    }

    /**
     * Read a composition from a share link (URL-safe base64 JSON), or start from the starter unit.
     *
     * @return Configuration
     */
    public static function fromShareCode(string $code): array
    {
        $json = base64_decode(strtr($code, '-_', '+/'), true);
        $input = $json === false ? null : json_decode($json, true);

        return self::normalize(is_array($input) && filled($input['size'] ?? null) ? $input : self::STARTER);
    }

    /**
     * Fill in missing values and drop anything that doesn't fit the chosen size.
     *
     * @param  array<string, mixed>  $input
     * @return Configuration
     */
    public static function normalize(array $input): array
    {
        $size = self::pick($input['size'] ?? null, self::SIZES, '6x3');
        $modules = max(1, min(self::MAX_MODULES, (int) ($input['modules'] ?? 1)));
        $floors = max(1, min(self::MAX_FLOORS, (int) ($input['floors'] ?? 1)));

        $levels = [];
        foreach (array_slice(array_keys(self::LEVELS), 0, $floors) as $level) {
            foreach (array_keys(self::WALLS) as $wall) {
                $slots = array_values((array) ($input[$level][$wall] ?? []));
                $levels[$level][$wall] = array_map(
                    fn (int $index): string => self::pick($slots[$index] ?? null, self::SLOTS, self::EMPTY),
                    range(0, self::slotCount($size, $wall, $modules) - 1),
                );
            }
        }

        $colors = [];
        foreach (self::DEFAULT_COLORS as $name => $default) {
            $color = $input['colors'][$name] ?? null;
            $colors[$name] = is_string($color) && preg_match(self::COLOR_PATTERN, $color) ? self::nearestColor($color) : $default;
        }

        $background = self::pick($input['background'] ?? null, self::BACKGROUNDS, 'industrial');
        $stairs = $floors > 1 && filter_var($input['stairs'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return ['size' => $size, 'modules' => $modules, 'floors' => $floors, ...$levels, 'stairs' => $stairs, 'colors' => $colors, 'background' => $background];
    }

    /**
     * The available colour closest to the given hex code, so older configurations and AI suggestions fit the range.
     */
    private static function nearestColor(string $hex): string
    {
        $channels = fn (string $hex): array => array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $target = $channels($hex);

        return collect(array_keys(self::COLORS))
            ->sortBy(fn (string $color): int => array_sum(array_map(fn (int $a, int $b): int => ($a - $b) ** 2, $channels($color), $target)))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $allowed
     */
    private static function pick(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && array_key_exists($value, $allowed) ? $value : $default;
    }
}
