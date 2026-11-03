<?php

namespace App\Support;

/**
 * Where the interior of one layer goes, worked out from the configuration so nobody has to place it by hand.
 *
 * Every unit has ceiling lights: one per 3 m of length, in the middle of each unit's depth. The toilet cubicle and the
 * kitchenette stand on the ground floor only; there is an airco on every layer, one per unit.
 *
 * - The toilet cubicle takes a corner, the back corners first, where no door is in its walls.
 * - The kitchenette stands against the back wall, otherwise the front wall, clear of doors and of the cubicle.
 *   Windows are fine: their sill is at worktop height.
 * - Each airco hangs high on the right or left wall in the middle of its unit, clear of windows, doors and the cubicle.
 *
 * Positions are in metres on the floor plan seen from above, over the nominal size of all coupled units: x from the
 * left wall, y from the back wall (unit 1 is at the front). Every item lies against a wall (wall); the cubicle also
 * against a side wall (side). Matches resources/js/interior-layout.js.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 *
 * @phpstan-type Item array{type: string, wall: string, side?: string, x1: float, y1: float, x2: float, y2: float}
 * @phpstan-type Layout array{lights: list<array{x: float, y: float}>, items: list<Item>}
 */
final class InteriorLayout
{
    /** Thickness in metres of the walls, which the items stand against. */
    public const WALL = 0.1;

    /** Toilet cubicle: width along the back or front wall, depth along the side wall. */
    public const TOILET = ['width' => 1.0, 'depth' => 1.5];

    public const KITCHEN = ['length' => 1.8, 'depth' => 0.6];

    public const AIRCO = ['length' => 0.9, 'depth' => 0.25];

    private const EPSILON = 0.001;

    /**
     * @param  Configuration  $configuration
     * @return Layout
     */
    public static function for(array $configuration, string $level): array
    {
        $size = UnitConfiguration::SIZES[$configuration['size']];
        $length = $size['length'];
        $unitDepth = $size['depth'];
        $depth = $unitDepth * $configuration['modules'];
        $walls = $configuration[$level];
        $interior = $configuration['interior'] ?? [];
        $isGroundFloor = $level === array_key_first(UnitConfiguration::LEVELS);

        $lights = [];
        $lightsPerUnit = intdiv($length, 3);
        for ($module = 0; $module < $configuration['modules']; $module++) {
            for ($light = 0; $light < $lightsPerUnit; $light++) {
                $lights[] = ['x' => ($light + 0.5) * $length / $lightsPerUnit, 'y' => ($module + 0.5) * $unitDepth];
            }
        }

        $items = [];
        if ($isGroundFloor && in_array('toilet', $interior, true)) {
            $items[] = self::toilet($walls, $length, $depth);
        }
        if ($isGroundFloor && in_array('kitchen', $interior, true)) {
            $items[] = self::kitchen($walls, $length, $depth, $items);
        }
        if (in_array('airco', $interior, true)) {
            for ($module = 0; $module < $configuration['modules']; $module++) {
                $items[] = self::airco($walls, $length, $depth, ($module + 0.5) * $unitDepth, $items);
            }
        }

        return ['lights' => $lights, 'items' => $items];
    }

    /**
     * @param  array<string, list<string>>  $walls
     * @return Item
     */
    private static function toilet(array $walls, int $length, int $depth): array
    {
        $candidates = [];

        foreach ([['back', 'left'], ['back', 'right'], ['front', 'left'], ['front', 'right']] as [$wall, $side]) {
            [$x1, $x2] = $side === 'left' ? [self::WALL, self::WALL + self::TOILET['width']] : [$length - self::WALL - self::TOILET['width'], $length - self::WALL];
            [$y1, $y2] = $wall === 'back' ? [self::WALL, self::WALL + self::TOILET['depth']] : [$depth - self::WALL - self::TOILET['depth'], $depth - self::WALL];
            $toilet = ['type' => 'toilet', 'wall' => $wall, 'side' => $side, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];

            if (! self::hasOpening($walls, $wall, $x1, $x2, $length, $depth, [UnitConfiguration::DOOR])
                && ! self::hasOpening($walls, $side, $y1, $y2, $length, $depth, [UnitConfiguration::DOOR])) {
                return $toilet;
            }
            $candidates[] = $toilet;
        }

        return $candidates[0];
    }

    /**
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function kitchen(array $walls, int $length, int $depth, array $placed): array
    {
        $candidates = [];

        foreach (['back', 'front'] as $wall) {
            foreach (['right', 'left'] as $align) {
                [$x1, $x2] = $align === 'right' ? [$length - self::WALL - self::KITCHEN['length'], $length - self::WALL] : [self::WALL, self::WALL + self::KITCHEN['length']];
                [$y1, $y2] = $wall === 'back' ? [self::WALL, self::WALL + self::KITCHEN['depth']] : [$depth - self::WALL - self::KITCHEN['depth'], $depth - self::WALL];
                $candidates[] = ['type' => 'kitchen', 'wall' => $wall, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
            }
        }

        $free = array_values(array_filter($candidates, fn (array $kitchen): bool => ! self::overlapsAny($kitchen, $placed)));

        foreach ($free as $kitchen) {
            if (! self::hasOpening($walls, $kitchen['wall'], $kitchen['x1'], $kitchen['x2'], $length, $depth, [UnitConfiguration::DOOR])) {
                return $kitchen;
            }
        }

        return $free[0] ?? $candidates[0];
    }

    /**
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function airco(array $walls, int $length, int $depth, float $center, array $placed): array
    {
        $y1 = $center - self::AIRCO['length'] / 2;
        $y2 = $center + self::AIRCO['length'] / 2;
        $candidates = [];

        foreach (['right', 'left'] as $side) {
            [$x1, $x2] = $side === 'right' ? [$length - self::WALL - self::AIRCO['depth'], $length - self::WALL] : [self::WALL, self::WALL + self::AIRCO['depth']];
            $airco = ['type' => 'airco', 'wall' => $side, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];

            if (! self::hasOpening($walls, $side, $y1, $y2, $length, $depth, [UnitConfiguration::DOOR, UnitConfiguration::WINDOW]) && ! self::overlapsAny($airco, $placed)) {
                return $airco;
            }
            $candidates[] = $airco;
        }

        return $candidates[0];
    }

    /**
     * Whether a slot of one of the given types lies along a stretch of a wall, given as plan coordinates along that
     * wall: x for the front and back walls, y for the side walls. Slots run from the wall's left end seen from outside.
     *
     * @param  array<string, list<string>>  $walls
     * @param  list<string>  $types
     */
    private static function hasOpening(array $walls, string $wall, float $from, float $to, int $length, int $depth, array $types): bool
    {
        [$start, $end] = match ($wall) {
            'front', 'left' => [$from, $to],
            'back' => [$length - $to, $length - $from],
            'right' => [$depth - $to, $depth - $from],
        };

        for ($index = (int) floor($start + self::EPSILON); $index < $end - self::EPSILON; $index++) {
            if (in_array($walls[$wall][$index] ?? UnitConfiguration::EMPTY, $types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Item  $item
     * @param  list<Item>  $placed
     */
    private static function overlapsAny(array $item, array $placed): bool
    {
        foreach ($placed as $other) {
            if ($item['x1'] < $other['x2'] - self::EPSILON && $other['x1'] < $item['x2'] - self::EPSILON
                && $item['y1'] < $other['y2'] - self::EPSILON && $other['y1'] < $item['y2'] - self::EPSILON) {
                return true;
            }
        }

        return false;
    }
}
