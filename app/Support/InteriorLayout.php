<?php

namespace App\Support;

/**
 * Where the interior of one layer goes, worked out from the configuration so nobody has to place it by hand; every item
 * can still be dragged elsewhere on the floor plan, which is kept in the configuration's positions.
 *
 * Every unit has ceiling lights: one per 3 m of length, in the middle of each unit's depth. There is an airco and a
 * heater on every layer, one per unit; everything else stands on the ground floor only.
 *
 * - The toilet cubicle takes a corner, the back corners first, where no door is in its walls.
 * - The kitchenette stands against the back wall, otherwise the front wall, clear of doors and of the cubicle.
 *   Windows are fine: their sill is at worktop height.
 * - Each airco hangs high on the right or left wall in the middle of its unit, clear of windows, doors and the cubicle.
 * - Each heater stands low on the left or right wall in the middle of its unit, clear of doors.
 * - A counter stands inside a hatch, otherwise inside a window; a desk inside a window, otherwise against a wall.
 * - A chair stands in front of a desk, otherwise in front of a counter.
 * - Worktops and cabinets take the first free stretch of wall; cabinets are tall, so not under a window.
 * - Wall sockets are spread evenly along the walls, each network point right next to a socket.
 *
 * An item that was dragged keeps its place: wall items move to the nearest wall, the toilet to the nearest corner and
 * desks and chairs stand where they were put, turned to the nearest wall. Items that were not dragged are placed
 * around them.
 *
 * Positions are in metres on the floor plan seen from above, over the nominal size of all coupled units: x from the
 * left wall, y from the back wall (unit 1 is at the front). Every item lies against a wall (wall), or for desks and
 * chairs faces it; the cubicle also stands against a side wall (side). Matches resources/js/interior-layout.js.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 *
 * @phpstan-type Item array{id: string, type: string, wall: string, side?: string, x1: float, y1: float, x2: float, y2: float, tentative: bool}
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

    /**
     * Indicative sizes in metres of everything else: length along the wall it stands against or faces, depth into the
     * room, and the heights it takes up, which decide what may stand above or below it.
     *
     * @var array<string, array{length: float, depth: float, bottom: float, top: float}>
     */
    public const SIZES = [
        'toilet' => ['length' => 1.0, 'depth' => 1.5, 'bottom' => 0.0, 'top' => 2.1],
        'kitchen' => ['length' => 1.8, 'depth' => 0.6, 'bottom' => 0.0, 'top' => 0.9],
        'airco' => ['length' => 0.9, 'depth' => 0.25, 'bottom' => 2.0, 'top' => 2.3],
        'heater' => ['length' => 1.0, 'depth' => 0.12, 'bottom' => 0.15, 'top' => 0.75],
        'counter' => ['length' => 1.2, 'depth' => 0.6, 'bottom' => 0.0, 'top' => 1.1],
        'worktop' => ['length' => 1.6, 'depth' => 0.6, 'bottom' => 0.0, 'top' => 0.9],
        'cabinet' => ['length' => 0.8, 'depth' => 0.45, 'bottom' => 0.0, 'top' => 2.0],
        'desk' => ['length' => 1.4, 'depth' => 0.7, 'bottom' => 0.0, 'top' => 0.75],
        'chair' => ['length' => 0.6, 'depth' => 0.6, 'bottom' => 0.0, 'top' => 0.9],
        'socket' => ['length' => 0.16, 'depth' => 0.06, 'bottom' => 0.3, 'top' => 0.38],
        'network' => ['length' => 0.16, 'depth' => 0.06, 'bottom' => 0.3, 'top' => 0.38],
    ];

    /** Items that stand free in the room, turned towards the nearest wall, when they are dragged. */
    public const FREE = ['desk', 'chair'];

    /** Items on the wall that nothing collides with. */
    private const POINTS = ['socket', 'network'];

    /**
     * Openings an item may not stand in front of when it is placed automatically.
     *
     * @var array<string, list<string>>
     */
    private const AVOID = [
        'toilet' => [UnitConfiguration::DOOR],
        'kitchen' => [UnitConfiguration::DOOR, UnitConfiguration::HATCH],
        'airco' => [UnitConfiguration::DOOR, UnitConfiguration::WINDOW, UnitConfiguration::HATCH],
        'heater' => [UnitConfiguration::DOOR, UnitConfiguration::HATCH],
        'counter' => [UnitConfiguration::DOOR],
        'worktop' => [UnitConfiguration::DOOR, UnitConfiguration::HATCH],
        'cabinet' => [UnitConfiguration::DOOR, UnitConfiguration::WINDOW, UnitConfiguration::HATCH],
        'desk' => [UnitConfiguration::DOOR, UnitConfiguration::HATCH],
        'chair' => [UnitConfiguration::DOOR],
        'socket' => [UnitConfiguration::DOOR, UnitConfiguration::HATCH],
        'network' => [UnitConfiguration::DOOR, UnitConfiguration::HATCH],
    ];

    /** Distance in metres between the spots along the walls where wall sockets can go. */
    private const SOCKET_STEP = 0.5;

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
        $positions = $configuration['positions'] ?? [];
        $tentative = $configuration['tentative'] ?? [];

        $lights = [];
        $lightsPerUnit = intdiv($length, 3);
        for ($module = 0; $module < $configuration['modules']; $module++) {
            for ($light = 0; $light < $lightsPerUnit; $light++) {
                $lights[] = ['x' => ($light + 0.5) * $length / $lightsPerUnit, 'y' => ($module + 0.5) * $unitDepth];
            }
        }

        $planned = self::planned($configuration, $level);
        $room = ['walls' => $walls, 'length' => $length, 'depth' => $depth, 'unitDepth' => $unitDepth, 'counts' => array_count_values($planned)];

        // Dragged items first, so everything placed automatically keeps clear of them.
        $placed = [];
        foreach ($planned as $id => $type) {
            if (isset($positions[$id])) {
                $placed[$id] = self::moved($id, $type, $positions[$id], $length, $depth);
            }
        }

        foreach ($planned as $id => $type) {
            if (! isset($placed[$id])) {
                $placed[$id] = self::place($id, $type, $room, $placed);
            }
        }

        $items = [];
        foreach (array_keys($planned) as $id) {
            $items[] = $placed[$id] + ['tentative' => in_array($placed[$id]['type'], $tentative, true)];
        }

        return ['lights' => $lights, 'items' => $items];
    }

    /**
     * The ids of the items of one layer, such as "walls:airco:0", which the positions of dragged items are kept under.
     *
     * @param  Configuration  $configuration
     * @return list<string>
     */
    public static function itemIds(array $configuration, string $level): array
    {
        return array_keys(self::planned($configuration, $level));
    }

    /**
     * Every item of one layer in the order it is placed: item id => type.
     *
     * @param  Configuration  $configuration
     * @return array<string, string>
     */
    private static function planned(array $configuration, string $level): array
    {
        $interior = $configuration['interior'] ?? [];
        $isGroundFloor = $level === array_key_first(UnitConfiguration::LEVELS);
        $counts = [];

        if ($isGroundFloor && in_array('toilet', $interior, true)) {
            $counts['toilet'] = 1;
        }
        if ($isGroundFloor && in_array('kitchen', $interior, true)) {
            $counts['kitchen'] = 1;
        }
        // Counters and desks before the climate units, so they get the hatches and windows they belong at; chairs last
        // of the furniture, so they can go in front of the desks and counters.
        $furniture = $isGroundFloor ? $configuration['furniture'] ?? [] : [];
        foreach (['counter', 'desk'] as $type) {
            if (isset($furniture[$type])) {
                $counts[$type] = $furniture[$type];
            }
        }
        foreach (['airco', 'heater'] as $type) {
            if (in_array($type, $interior, true)) {
                $counts[$type] = $configuration['modules'];
            }
        }
        foreach (['worktop', 'cabinet', 'chair'] as $type) {
            if (isset($furniture[$type])) {
                $counts[$type] = $furniture[$type];
            }
        }
        if ($isGroundFloor) {
            $counts += $configuration['connections'] ?? [];
        }

        $planned = [];
        foreach ($counts as $type => $count) {
            for ($index = 0; $index < $count; $index++) {
                $planned["{$level}:{$type}:{$index}"] = $type;
            }
        }

        return $planned;
    }

    /**
     * Where an item goes when it was not dragged.
     *
     * @param  array{walls: array<string, list<string>>, length: int, depth: int, unitDepth: int, counts: array<string, int>}  $room
     * @param  array<string, Item>  $placed
     * @return Item
     */
    private static function place(string $id, string $type, array $room, array $placed): array
    {
        ['walls' => $walls, 'length' => $length, 'depth' => $depth, 'unitDepth' => $unitDepth] = $room;
        $openings = fn (string $openingType): array => self::openingCentres($walls, $openingType, $length, $depth, $unitDepth);
        $index = (int) substr($id, strrpos($id, ':') + 1);
        $placed = array_values($placed);

        return match ($type) {
            'toilet' => self::toilet($id, $walls, $length, $depth, $placed),
            'kitchen' => self::kitchen($id, $walls, $length, $depth, $placed),
            'airco' => self::airco($id, $walls, $length, $depth, ($index + 0.5) * $unitDepth, $placed),
            'heater' => self::onSideWall($id, 'heater', ['left', 'right'], $walls, $length, $depth, ($index + 0.5) * $unitDepth, $placed)
                ?? self::alongWalls($id, 'heater', ['left', 'right', 'back', 'front'], $walls, $length, $depth, $placed),
            'counter' => self::atOpening($id, 'counter', [...$openings(UnitConfiguration::HATCH), ...$openings(UnitConfiguration::WINDOW)], $walls, $length, $depth, $placed),
            'desk' => self::atOpening($id, 'desk', $openings(UnitConfiguration::WINDOW), $walls, $length, $depth, $placed),
            'chair' => self::chair($id, $index, $walls, $length, $depth, $placed),
            'worktop' => self::alongWalls($id, 'worktop', ['back', 'front', 'left', 'right'], $walls, $length, $depth, $placed),
            'cabinet' => self::alongWalls($id, 'cabinet', ['back', 'left', 'right', 'front'], $walls, $length, $depth, $placed),
            'socket', 'network' => self::connection($id, $type, $index, $room['counts'][$type], $walls, $length, $depth, $placed),
        };
    }

    /**
     * A dragged item at its new position: against the nearest wall, in the nearest corner or, for desks and chairs,
     * free in the room turned towards the nearest wall; always within the walls.
     *
     * @param  array{x: float, y: float}  $position
     * @return Item
     */
    private static function moved(string $id, string $type, array $position, int $length, int $depth): array
    {
        ['x' => $x, 'y' => $y] = $position;

        if ($type === 'toilet') {
            return self::corner($id, $y < $depth / 2 ? 'back' : 'front', $x < $length / 2 ? 'left' : 'right', $length, $depth);
        }

        $distances = ['back' => $y, 'front' => $depth - $y, 'left' => $x, 'right' => $length - $x];
        $wall = array_search(min($distances), $distances, true);

        if (in_array($type, self::FREE, true)) {
            return self::free($id, $type, $wall, $x, $y, $length, $depth);
        }

        return self::againstWall($id, $type, $wall, in_array($wall, ['front', 'back'], true) ? $x : $y, $length, $depth);
    }

    /**
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function toilet(string $id, array $walls, int $length, int $depth, array $placed): array
    {
        $candidates = [];

        foreach ([['back', 'left'], ['back', 'right'], ['front', 'left'], ['front', 'right']] as [$wall, $side]) {
            $toilet = self::corner($id, $wall, $side, $length, $depth);

            if (! self::hasOpening($walls, $wall, $toilet['x1'], $toilet['x2'], $length, $depth, self::AVOID['toilet'])
                && ! self::hasOpening($walls, $side, $toilet['y1'], $toilet['y2'], $length, $depth, self::AVOID['toilet'])
                && ! self::overlapsAny($toilet, $placed)) {
                return $toilet;
            }
            $candidates[] = $toilet;
        }

        return $candidates[0];
    }

    /**
     * @return Item
     */
    private static function corner(string $id, string $wall, string $side, int $length, int $depth): array
    {
        [$x1, $x2] = $side === 'left' ? [self::WALL, self::WALL + self::TOILET['width']] : [$length - self::WALL - self::TOILET['width'], $length - self::WALL];
        [$y1, $y2] = $wall === 'back' ? [self::WALL, self::WALL + self::TOILET['depth']] : [$depth - self::WALL - self::TOILET['depth'], $depth - self::WALL];

        return ['id' => $id, 'type' => 'toilet', 'wall' => $wall, 'side' => $side, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
    }

    /**
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function kitchen(string $id, array $walls, int $length, int $depth, array $placed): array
    {
        $candidates = [];

        foreach (['back', 'front'] as $wall) {
            foreach (['right', 'left'] as $align) {
                [$x1, $x2] = $align === 'right' ? [$length - self::WALL - self::KITCHEN['length'], $length - self::WALL] : [self::WALL, self::WALL + self::KITCHEN['length']];
                [$y1, $y2] = $wall === 'back' ? [self::WALL, self::WALL + self::KITCHEN['depth']] : [$depth - self::WALL - self::KITCHEN['depth'], $depth - self::WALL];
                $candidates[] = ['id' => $id, 'type' => 'kitchen', 'wall' => $wall, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
            }
        }

        $free = array_values(array_filter($candidates, fn (array $kitchen): bool => ! self::overlapsAny($kitchen, $placed)));

        foreach ($free as $kitchen) {
            if (! self::hasOpening($walls, $kitchen['wall'], $kitchen['x1'], $kitchen['x2'], $length, $depth, self::AVOID['kitchen'])) {
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
    private static function airco(string $id, array $walls, int $length, int $depth, float $center, array $placed): array
    {
        return self::onSideWall($id, 'airco', ['right', 'left'], $walls, $length, $depth, $center, $placed)
            ?? self::againstWall($id, 'airco', 'right', $center, $length, $depth);
    }

    /**
     * An item in the middle of its unit on the first of the given side walls where it is clear of the openings it
     * avoids and of other items, if there is one.
     *
     * @param  list<string>  $sides
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item|null
     */
    private static function onSideWall(string $id, string $type, array $sides, array $walls, int $length, int $depth, float $center, array $placed): ?array
    {
        foreach ($sides as $side) {
            $item = self::againstWall($id, $type, $side, $center, $length, $depth);

            if (! self::hasOpening($walls, $side, $item['y1'], $item['y2'], $length, $depth, self::AVOID[$type]) && ! self::overlapsAny($item, $placed)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * An item centred inside the first of the given openings where it is clear of doors and other items; otherwise on
     * the first free stretch of wall.
     *
     * @param  list<array{string, float}>  $openings  [wall, position along the wall on the plan] of each opening.
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function atOpening(string $id, string $type, array $openings, array $walls, int $length, int $depth, array $placed): array
    {
        foreach ($openings as [$wall, $along]) {
            $item = self::againstWall($id, $type, $wall, $along, $length, $depth);

            if (! self::blocks($walls, $item, [UnitConfiguration::DOOR], $length, $depth) && ! self::overlapsAny($item, $placed)) {
                return $item;
            }
        }

        return self::alongWalls($id, $type, ['front', 'back', 'left', 'right'], $walls, $length, $depth, $placed);
    }

    /**
     * A chair in front of the desk with the same number, otherwise in front of a counter, otherwise against a wall.
     *
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function chair(string $id, int $index, array $walls, int $length, int $depth, array $placed): array
    {
        $ofType = function (string $type) use ($placed): array {
            $items = array_values(array_filter($placed, fn (array $item): bool => $item['type'] === $type));
            usort($items, fn (array $a, array $b): int => strnatcmp($a['id'], $b['id']));

            return $items;
        };
        $tables = [...$ofType('desk'), ...$ofType('counter')];
        $chair = self::SIZES['chair'];

        if (isset($tables[$index])) {
            $table = $tables[$index];
            $centerX = ($table['x1'] + $table['x2']) / 2;
            $centerY = ($table['y1'] + $table['y2']) / 2;
            $offset = $chair['depth'] / 2 - 0.15;

            [$x, $y] = match ($table['wall']) {
                'back' => [$centerX, $table['y2'] + $offset],
                'front' => [$centerX, $table['y1'] - $offset],
                'left' => [$table['x2'] + $offset, $centerY],
                'right' => [$table['x1'] - $offset, $centerY],
            };

            return self::free($id, 'chair', $table['wall'], $x, $y, $length, $depth);
        }

        return self::alongWalls($id, 'chair', ['back', 'front', 'left', 'right'], $walls, $length, $depth, $placed);
    }

    /**
     * An item on the first stretch of the given walls, in that order and every 10 cm from the wall's left end on the
     * plan, that is clear of the openings it avoids and of other items; otherwise on the first spot that is clear of
     * other items, or the very first spot.
     *
     * @param  list<string>  $order
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function alongWalls(string $id, string $type, array $order, array $walls, int $length, int $depth, array $placed): array
    {
        $itemLength = self::SIZES[$type]['length'];
        $clear = null;
        $first = null;

        foreach ($order as $wall) {
            $wallLength = in_array($wall, ['front', 'back'], true) ? $length : $depth;
            $steps = (int) floor(($wallLength - 2 * self::WALL - $itemLength) * 10 + self::EPSILON);

            for ($step = 0; $step <= $steps; $step++) {
                $item = self::againstWall($id, $type, $wall, self::WALL + $itemLength / 2 + $step / 10, $length, $depth);
                $first ??= $item;

                if (self::overlapsAny($item, $placed)) {
                    continue;
                }
                $clear ??= $item;

                if (! self::blocks($walls, $item, self::AVOID[$type], $length, $depth)) {
                    return $item;
                }
            }
        }

        return $clear ?? $first;
    }

    /**
     * Wall sockets spread evenly over the spots along all walls that are clear of doors, hatches and the toilet
     * cubicle; a network point goes just to the right of the socket at the same share of the walls.
     *
     * @param  array<string, list<string>>  $walls
     * @param  list<Item>  $placed
     * @return Item
     */
    private static function connection(string $id, string $type, int $index, int $count, array $walls, int $length, int $depth, array $placed): array
    {
        $cubicles = array_values(array_filter($placed, fn (array $item): bool => $item['type'] === 'toilet'));

        $spots = [];

        foreach (['front', 'right', 'back', 'left'] as $wall) {
            $wallLength = in_array($wall, ['front', 'back'], true) ? $length : $depth;
            $spotCount = (int) round($wallLength / self::SOCKET_STEP) - 1;

            for ($spot = 1; $spot <= $spotCount; $spot++) {
                $along = $spot * self::SOCKET_STEP + ($type === 'network' ? 0.25 : 0);
                $item = self::againstWall($id, $type, $wall, $along, $length, $depth);
                $inCubicle = array_filter($cubicles, fn (array $cubicle): bool => $item['x1'] < $cubicle['x2'] && $cubicle['x1'] < $item['x2'] && $item['y1'] < $cubicle['y2'] && $cubicle['y1'] < $item['y2']);
                if (! $inCubicle && ! self::blocks($walls, $item, self::AVOID[$type], $length, $depth)) {
                    $spots[] = $item;
                }
            }
        }

        if ($spots === []) {
            return self::againstWall($id, $type, 'back', $length / 2, $length, $depth);
        }

        return $spots[(int) floor(($index + 0.5) * count($spots) / $count) % count($spots)];
    }

    /**
     * An item flush against a wall, centred at the given position along it on the plan (x for the front and back
     * walls, y for the side walls) but kept within the walls.
     *
     * @return Item
     */
    private static function againstWall(string $id, string $type, string $wall, float $along, int $length, int $depth): array
    {
        ['length' => $itemLength, 'depth' => $itemDepth] = self::SIZES[$type];
        $isFrontOrBack = in_array($wall, ['front', 'back'], true);
        $wallLength = $isFrontOrBack ? $length : $depth;
        $along = max(self::WALL + $itemLength / 2, min($wallLength - self::WALL - $itemLength / 2, $along));
        [$from, $to] = [$along - $itemLength / 2, $along + $itemLength / 2];

        [$x1, $y1, $x2, $y2] = match ($wall) {
            'back' => [$from, self::WALL, $to, self::WALL + $itemDepth],
            'front' => [$from, $depth - self::WALL - $itemDepth, $to, $depth - self::WALL],
            'left' => [self::WALL, $from, self::WALL + $itemDepth, $to],
            'right' => [$length - self::WALL - $itemDepth, $from, $length - self::WALL, $to],
        };

        return ['id' => $id, 'type' => $type, 'wall' => $wall, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
    }

    /**
     * A desk or chair free in the room, centred on the given point but kept within the walls, with its length along
     * the wall it faces.
     *
     * @return Item
     */
    private static function free(string $id, string $type, string $wall, float $x, float $y, int $length, int $depth): array
    {
        ['length' => $itemLength, 'depth' => $itemDepth] = self::SIZES[$type];
        [$halfX, $halfY] = in_array($wall, ['front', 'back'], true) ? [$itemLength / 2, $itemDepth / 2] : [$itemDepth / 2, $itemLength / 2];
        $x = max(self::WALL + $halfX, min($length - self::WALL - $halfX, $x));
        $y = max(self::WALL + $halfY, min($depth - self::WALL - $halfY, $y));

        return ['id' => $id, 'type' => $type, 'wall' => $wall, 'x1' => $x - $halfX, 'y1' => $y - $halfY, 'x2' => $x + $halfX, 'y2' => $y + $halfY];
    }

    /**
     * The middle of every opening of a type, as [wall, position along the wall on the plan], wall by wall.
     *
     * @param  array<string, list<string>>  $walls
     * @return list<array{string, float}>
     */
    private static function openingCentres(array $walls, string $type, int $length, int $depth, int $unitDepth): array
    {
        $centres = [];

        foreach (['front', 'right', 'back', 'left'] as $wall) {
            $isFrontOrBack = in_array($wall, ['front', 'back'], true);
            $wallLength = $isFrontOrBack ? $length : $depth;
            $segment = $isFrontOrBack ? $length : $unitDepth;

            foreach (UnitConfiguration::openings($walls[$wall], $segment) as $opening) {
                if ($opening['type'] !== $type) {
                    continue;
                }

                // Openings are measured from the wall's left end as seen from outside; the plan runs the other way
                // along the back and right walls.
                $middle = ($opening['left'] + $opening['right']) / 2;
                $centres[] = [$wall, in_array($wall, ['back', 'right'], true) ? $wallLength - $middle : $middle];
            }
        }

        return $centres;
    }

    /**
     * Whether an opening of one of the given types lies along the stretch of its wall an item takes.
     *
     * @param  array<string, list<string>>  $walls
     * @param  Item  $item
     * @param  list<string>  $types
     */
    private static function blocks(array $walls, array $item, array $types, int $length, int $depth): bool
    {
        [$from, $to] = in_array($item['wall'], ['front', 'back'], true) ? [$item['x1'], $item['x2']] : [$item['y1'], $item['y2']];

        return self::hasOpening($walls, $item['wall'], $from, $to, $length, $depth, $types);
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
     * Whether an item takes up the same floor space at the same height as one already placed. Wall sockets, network
     * points and chairs never get in the way.
     *
     * @param  Item  $item
     * @param  list<Item>  $placed
     */
    private static function overlapsAny(array $item, array $placed): bool
    {
        if (in_array($item['type'], self::POINTS, true)) {
            return false;
        }

        $size = self::SIZES[$item['type']];

        foreach ($placed as $other) {
            // A chair is moved aside or tucked under, so it never pushes anything else away.
            if (in_array($other['type'], [...self::POINTS, 'chair'], true)) {
                continue;
            }

            $otherSize = self::SIZES[$other['type']];
            if ($item['x1'] < $other['x2'] - self::EPSILON && $other['x1'] < $item['x2'] - self::EPSILON
                && $item['y1'] < $other['y2'] - self::EPSILON && $other['y1'] < $item['y2'] - self::EPSILON
                && $size['bottom'] < $otherSize['top'] - self::EPSILON && $otherSize['bottom'] < $size['top'] - self::EPSILON) {
                return true;
            }
        }

        return false;
    }
}
