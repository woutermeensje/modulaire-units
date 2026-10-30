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
    /**
     * Nominal sizes in metres (one slot per metre) and the real outside size in millimetres of one unit.
     *
     * @var array<string, array{label: string, length: int, depth: int, outer: array{length: int, depth: int}}>
     */
    public const SIZES = [
        '3x3' => ['label' => '3 x 3 meter', 'length' => 3, 'depth' => 3, 'outer' => ['length' => 2990, 'depth' => 2990]],
        '6x3' => ['label' => '6 x 3 meter', 'length' => 6, 'depth' => 3, 'outer' => ['length' => 6058, 'depth' => 2990]],
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

    /**
     * Outside sizes in metres of the window and door frames, the height their bottom stands at from the bottom of the
     * wall (the floor is at 0.15), and how many 1 m slots they take. A window covers two neighbouring slots.
     *
     * @var array<string, array{width: float, height: float, bottom: float, slots: int}>
     */
    public const OPENING_SIZES = [
        self::WINDOW => ['width' => 1.8, 'height' => 1.2, 'bottom' => 0.9, 'slots' => 2],
        self::DOOR => ['width' => 0.875, 'height' => 2.125, 'bottom' => 0.15, 'slots' => 1],
    ];

    /** Clear opening in metres of the door frame. */
    public const DOOR_CLEAR = ['width' => 0.811, 'height' => 2.093];

    /** Distance in metres openings keep from wall ends and from seams between coupled units, where the frame posts stand. */
    public const POST_CLEARANCE = 0.12;

    /** @var array<string, string> */
    public const BACKGROUNDS = [
        'industrial' => 'Industrieterrein',
        'meadow' => 'Grasveld',
        'site' => 'Bouwterrein',
        'plain' => 'Neutraal',
    ];

    /**
     * The colours the walls and the frame can have: hex code => name (white RAL 9010, anthracite RAL 7016, blue RAL 5010,
     * and the light blue of "Huisvesting" in the logo).
     *
     * @var array<string, string>
     */
    public const COLORS = [
        '#f2f0e8' => 'Wit',
        '#383e42' => 'Antraciet',
        '#0e4c92' => 'Blauw',
        '#4188aa' => 'Logoblauw',
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
            'front' => ['empty', 'window', 'window', 'window', 'window', 'empty'],
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
     * Real outside size in millimetres of all coupled units together, as used on the floor plan.
     *
     * @param  Configuration  $configuration
     * @return array{length: int, depth: int}
     */
    public static function outerSize(array $configuration): array
    {
        $outer = self::SIZES[$configuration['size']]['outer'];

        return ['length' => $outer['length'], 'depth' => $outer['depth'] * $configuration['modules']];
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
                $levels[$level][$wall] = self::pairWindows(array_map(
                    fn (int $index): string => self::pick($slots[$index] ?? null, self::SLOTS, self::EMPTY),
                    range(0, self::slotCount($size, $wall, $modules) - 1),
                ), self::slotCount($size, $wall));
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
     * Where the windows and doors of a wall are, in metres from its left end (seen from outside) and from the bottom of
     * the wall. Each opening is centred on its slots, but kept clear of the posts at wall ends and seams.
     *
     * Neighbouring window slots form windows of two slots, from left to right; when a run of window slots has an odd
     * length its last window takes three slots, so a window of three slots sits centred on the middle one.
     *
     * @param  list<string>  $slots  Normalized slots of one wall.
     * @param  int  $segment  Slots per unit along this wall, see slotCount() for a single unit.
     * @return list<array{type: string, left: float, right: float, bottom: float, top: float}>
     */
    public static function openings(array $slots, int $segment): array
    {
        $openings = [];
        $index = 0;

        while ($index < count($slots)) {
            $type = $slots[$index];
            $size = self::OPENING_SIZES[$type] ?? null;

            if ($size === null) {
                $index++;

                continue;
            }

            $start = intdiv($index, $segment) * $segment;
            $spans = [$size['slots']];

            if ($type === self::WINDOW) {
                $end = $index;
                while ($end < min(count($slots), $start + $segment) && $slots[$end] === self::WINDOW) {
                    $end++;
                }
                $spans = self::windowSpans($end - $index);
            }

            foreach ($spans as $span) {
                $left = $index + ($span - $size['width']) / 2;
                $left = min(max($left, $start + self::POST_CLEARANCE), $start + $segment - self::POST_CLEARANCE - $size['width']);
                $openings[] = ['type' => $type, 'left' => $left, 'right' => $left + $size['width'], 'bottom' => $size['bottom'], 'top' => $size['bottom'] + $size['height']];
                $index += $span;
            }
        }

        return $openings;
    }

    /**
     * How a run of neighbouring window slots is split into windows: two slots each, the last one three when the run
     * is odd, e.g. 3 => [3], 4 => [2, 2], 5 => [2, 3].
     *
     * @return list<int>
     */
    public static function windowSpans(int $length): array
    {
        if ($length < 2) {
            return [];
        }

        $spans = array_fill(0, intdiv($length, 2), 2);
        if ($length % 2 === 1) {
            $spans[count($spans) - 1] = 3;
        }

        return $spans;
    }

    /**
     * Make every window take at least two neighbouring slots within one unit: a lone window slot takes the next slot
     * (or the previous one) when that is free, and is dropped when it can't. Runs of window slots stop at seams.
     *
     * @param  list<string>  $slots
     * @return list<string>
     */
    private static function pairWindows(array $slots, int $segment): array
    {
        $index = 0;

        while ($index < count($slots)) {
            if ($slots[$index] !== self::WINDOW) {
                $index++;

                continue;
            }

            $segmentStart = intdiv($index, $segment) * $segment;
            $segmentEnd = min(count($slots), $segmentStart + $segment);
            $end = $index;
            while ($end < $segmentEnd && $slots[$end] === self::WINDOW) {
                $end++;
            }

            if ($end - $index === 1) {
                $next = $index + 1;
                $previous = $index - 1;

                if ($next < $segmentEnd && $slots[$next] === self::EMPTY) {
                    $slots[$next] = self::WINDOW;
                    $end = $next + 1;
                } elseif ($previous >= $segmentStart && $slots[$previous] === self::EMPTY) {
                    $slots[$previous] = self::WINDOW;
                } else {
                    $slots[$index] = self::EMPTY;
                }
            }

            $index = $end;
        }

        return $slots;
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
