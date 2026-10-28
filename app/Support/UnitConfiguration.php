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
 * @phpstan-type Configuration array{
 *     size: string,
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
        'meadow' => 'Grasveld',
        'site' => 'Bouwterrein',
        'plain' => 'Neutraal',
    ];

    /** @var array{cladding: string, frame: string} */
    public const DEFAULT_COLORS = ['cladding' => '#3d4a52', 'frame' => '#1f2326'];

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
            "{$key}.walls" => ['array'],
            "{$key}.walls.*" => ['array'],
            "{$key}.walls.*.*" => [Rule::in(array_keys(self::SLOTS))],
            "{$key}.colors" => ['array'],
            "{$key}.colors.*" => ['nullable', 'string', 'regex:'.self::COLOR_PATTERN],
            "{$key}.background" => ['nullable', Rule::in(array_keys(self::BACKGROUNDS))],
        ];
    }

    /**
     * Number of 1 m slots on a wall.
     */
    public static function slotCount(string $size, string $wall): int
    {
        $dimensions = self::SIZES[$size] ?? self::SIZES['3x3'];

        return in_array($wall, ['front', 'back'], true) ? $dimensions['length'] : $dimensions['depth'];
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

        $walls = [];
        foreach (array_keys(self::WALLS) as $wall) {
            $slots = array_values((array) ($input['walls'][$wall] ?? []));
            $walls[$wall] = array_map(
                fn (int $index): string => self::pick($slots[$index] ?? null, self::SLOTS, self::EMPTY),
                range(0, self::slotCount($size, $wall) - 1),
            );
        }

        $colors = [];
        foreach (self::DEFAULT_COLORS as $name => $default) {
            $color = $input['colors'][$name] ?? null;
            $colors[$name] = is_string($color) && preg_match(self::COLOR_PATTERN, $color) ? strtolower($color) : $default;
        }

        $background = self::pick($input['background'] ?? null, self::BACKGROUNDS, 'meadow');

        return ['size' => $size, 'walls' => $walls, 'colors' => $colors, 'background' => $background];
    }

    /**
     * @param  array<string, mixed>  $allowed
     */
    private static function pick(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && array_key_exists($value, $allowed) ? $value : $default;
    }
}
