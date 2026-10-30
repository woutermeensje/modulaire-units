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
 * @phpstan-type Configuration array{
 *     size: string,
 *     modules: int,
 *     walls: array{front: list<string>, back: list<string>, left: list<string>, right: list<string>},
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
            "{$key}.walls" => ['array'],
            "{$key}.walls.*" => ['array'],
            "{$key}.walls.*.*" => [Rule::in(array_keys(self::SLOTS))],
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
     * @return array{length: int, depth: int}
     */
    public static function dimensions(array $configuration): array
    {
        $size = self::SIZES[$configuration['size']];

        return ['length' => $size['length'], 'depth' => $size['depth'] * $configuration['modules']];
    }

    /**
     * A description such as "Modulaire unit van 6 x 3 meter" or "2 gekoppelde units van 6 x 3 meter (6 x 6 meter)".
     *
     * @param  Configuration  $configuration
     */
    public static function label(array $configuration): string
    {
        $size = self::SIZES[$configuration['size']]['label'];

        if ($configuration['modules'] === 1) {
            return 'Modulaire unit van '.$size;
        }

        $dimensions = self::dimensions($configuration);

        return "{$configuration['modules']} gekoppelde units van {$size} ({$dimensions['length']} x {$dimensions['depth']} meter)";
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
     * Fill in missing values and drop anything that doesn't fit the chosen size.
     *
     * @param  array<string, mixed>  $input
     * @return Configuration
     */
    public static function normalize(array $input): array
    {
        $size = self::pick($input['size'] ?? null, self::SIZES, '6x3');
        $modules = max(1, min(self::MAX_MODULES, (int) ($input['modules'] ?? 1)));

        $walls = [];
        foreach (array_keys(self::WALLS) as $wall) {
            $slots = array_values((array) ($input['walls'][$wall] ?? []));
            $walls[$wall] = array_map(
                fn (int $index): string => self::pick($slots[$index] ?? null, self::SLOTS, self::EMPTY),
                range(0, self::slotCount($size, $wall, $modules) - 1),
            );
        }

        $colors = [];
        foreach (self::DEFAULT_COLORS as $name => $default) {
            $color = $input['colors'][$name] ?? null;
            $colors[$name] = is_string($color) && preg_match(self::COLOR_PATTERN, $color) ? self::nearestColor($color) : $default;
        }

        $background = self::pick($input['background'] ?? null, self::BACKGROUNDS, 'industrial');

        return ['size' => $size, 'modules' => $modules, 'walls' => $walls, 'colors' => $colors, 'background' => $background];
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
