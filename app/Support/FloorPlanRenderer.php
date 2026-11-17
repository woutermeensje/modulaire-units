<?php

namespace App\Support;

/**
 * Draws a unit configuration as a technical floor plan seen from above: white background, black lines, walls in grey,
 * windows and doors in their openings and all dimensions in millimetres. The front wall is at the bottom, as in the
 * configurator. With two layers both floors are drawn side by side.
 *
 * The interior is drawn with the usual symbols: ceiling lights (always fitted) as a crossed circle, the toilet cubicle,
 * the kitchenette, every airco (high on the wall and therefore dashed), heaters, furniture, wall sockets and network
 * points. Items that are still uncertain are drawn faded with "optie". See InteriorLayout.
 *
 * Every item is a group with data-item (its id) and its centre in nominal metres (data-x, data-y), and every floor a
 * group with data-plan-level and its scale in millimetres per nominal metre, so the configurator can drag items on it.
 *
 * The drawing is indicative: it shows the composition, not a technically approved design.
 *
 * Drawing units are millimetres; the SVG scales to its container through the viewBox.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
class FloorPlanRenderer
{
    /** Wall thickness. */
    private const WALL = 100;

    /** Room around each plan for dimension lines. */
    private const MARGIN = 1600;

    /** Extra room above the plans for the floor titles. */
    private const TITLE = 500;

    private const FONT = 'Arial, Helvetica, sans-serif';

    private const TEXT_SIZE = 150;

    private const INK = '#111111';

    private const WALL_FILL = '#c9c9c9';

    /** The step outside a door, in metres: indicative, as the real one depends on the ground. */
    public const STEP = ['width' => 1.0, 'depth' => 0.4, 'height' => 0.15];

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function render(array $configuration): string
    {
        $configuration = UnitConfiguration::normalize($configuration);
        ['length' => $length, 'depth' => $depth] = UnitConfiguration::outerSize($configuration);
        $levels = array_slice(UnitConfiguration::LEVELS, 0, $configuration['floors'], true);
        $titled = count($levels) > 1;

        $planWidth = $length + 2 * self::MARGIN;
        $top = self::MARGIN + ($titled ? self::TITLE : 0);
        $width = $planWidth * count($levels);
        $size = UnitConfiguration::SIZES[$configuration['size']];
        $scaleX = $length / $size['length'];
        $scaleY = $depth / ($size['depth'] * $configuration['modules']);

        $body = '';
        foreach (array_keys($levels) as $index => $level) {
            $originX = $index * $planWidth + self::MARGIN;
            if ($titled) {
                $body .= $this->text($originX + $length / 2, 320, __($levels[$level]), 'font-size="220" font-weight="bold"');
            }
            $body .= '<g transform="translate('.$originX.' '.$top.')" data-plan-level="'.$level.'" data-scale-x="'.$this->number($scaleX).'" data-scale-y="'.$this->number($scaleY).'">'
                .$this->plan($configuration, $level, $length, $depth).'</g>';
        }

        [$legend, $legendHeight] = $this->legend($configuration, $width, $top + $depth + self::MARGIN - 200);
        $height = $top + $depth + self::MARGIN + $legendHeight;
        $label = UnitConfiguration::label($configuration);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.e($label).'">'
            .'<title>'.e($label).'</title>'
            .'<rect width="'.$width.'" height="'.$height.'" fill="#ffffff"/>'
            .$body
            .$legend
            .'</svg>';
    }

    /**
     * One floor: walls with their openings, seams between coupled units and the overall dimensions.
     *
     * @param  Configuration  $configuration
     */
    private function plan(array $configuration, string $level, int $length, int $depth): string
    {
        $svg = $this->seams($configuration, $length, $depth).$this->interior($configuration, $level, $length, $depth);
        $size = UnitConfiguration::SIZES[$configuration['size']];

        foreach (array_keys(UnitConfiguration::WALLS) as $wall) {
            $isSide = in_array($wall, ['left', 'right'], true);
            $wallLength = $isSide ? $depth : $length;
            $nominal = $isSide ? $size['depth'] * $configuration['modules'] : $size['length'];
            $scale = $wallLength / ($nominal * 1000);

            $openings = array_map(function (array $opening) use ($scale): array {
                $center = ($opening['left'] + $opening['right']) / 2 * 1000 * $scale;
                $width = ($opening['right'] - $opening['left']) * 1000;

                return ['type' => $opening['type'], 'from' => $center - $width / 2, 'width' => $width, 'height' => ($opening['top'] - $opening['bottom']) * 1000];
            }, UnitConfiguration::openings($configuration[$level][$wall], UnitConfiguration::slotCount($configuration['size'], $wall)));

            $steps = ($configuration['steps'] ?? false) && $level === array_key_first(UnitConfiguration::LEVELS);
            $svg .= $this->wall($wall, $wallLength, $length, $depth, $openings, $steps);
        }

        return $svg.$this->overallDimensions($length, $depth);
    }

    /**
     * The wall as a grey band with gaps for its openings, and a window or door drawn in each gap.
     *
     * Every wall is drawn in its own frame: x runs along the outside face from the wall's left end as seen from
     * outside, y points into the unit. The frame is then turned into place on the plan.
     *
     * @param  list<array{type: string, from: float, width: float, height: float}>  $openings
     * @param  bool  $steps  Whether a step stands outside every door.
     */
    private function wall(string $wall, int $wallLength, int $length, int $depth, array $openings, bool $steps = false): string
    {
        $transform = match ($wall) {
            'front' => "matrix(1 0 0 -1 0 {$depth})",
            'back' => "matrix(-1 0 0 1 {$length} 0)",
            'left' => 'matrix(0 1 1 0 0 0)',
            'right' => "matrix(0 -1 -1 0 {$length} {$depth})",
        };

        // The front and back walls run the full length; the side walls fit between them.
        $isSide = in_array($wall, ['left', 'right'], true);
        $start = $isSide ? self::WALL : 0;
        $end = $isSide ? $wallLength - self::WALL : $wallLength;

        $svg = '';
        $position = $start;
        foreach ($openings as $opening) {
            $svg .= $this->band($position, $opening['from']);
            $position = $opening['from'] + $opening['width'];
        }
        $svg .= $this->band($position, $end);

        $labels = '';
        foreach ($openings as $opening) {
            $svg .= match ($opening['type']) {
                UnitConfiguration::DOOR => $this->door($opening).($steps ? $this->step($opening) : ''),
                UnitConfiguration::HATCH => $this->hatch($opening),
                default => $this->window($opening),
            };
            $labels .= $this->openingLabels($wall, $opening, $wallLength, $length, $depth);
        }

        return '<g transform="'.$transform.'">'.$svg.'</g>'.$labels;
    }

    private function band(float $from, float $to): string
    {
        if ($to - $from < 1) {
            return '';
        }

        return '<rect x="'.$this->number($from).'" y="0" width="'.$this->number($to - $from).'" height="'.self::WALL.'" fill="'.self::WALL_FILL.'" stroke="'.self::INK.'" stroke-width="10"/>';
    }

    /**
     * A window: the glass as a double line in the middle of the wall, and a dimension line outside.
     *
     * @param  array{from: float, width: float}  $opening
     */
    private function window(array $opening): string
    {
        ['from' => $from, 'width' => $width] = $opening;
        $to = $from + $width;
        $middle = self::WALL / 2;

        return $this->line($from, 0, $from, self::WALL, 10)
            .$this->line($to, 0, $to, self::WALL, 10)
            .$this->line($from, $middle - 15, $to, $middle - 15, 6)
            .$this->line($from, $middle + 15, $to, $middle + 15, 6)
            .$this->dimensionLine($from, $to, -350);
    }

    /**
     * A door: the leaf opened outwards from its left jamb and the arc it swings through.
     *
     * @param  array{from: float, width: float}  $opening
     */
    private function door(array $opening): string
    {
        ['from' => $from, 'width' => $width] = $opening;
        $clear = UnitConfiguration::DOOR_CLEAR['width'] * 1000;
        $jamb = ($width - $clear) / 2;
        $hinge = $from + $jamb;

        return '<rect x="'.$this->number($from).'" y="0" width="'.$this->number($jamb).'" height="'.self::WALL.'" fill="'.self::INK.'"/>'
            .'<rect x="'.$this->number($from + $width - $jamb).'" y="0" width="'.$this->number($jamb).'" height="'.self::WALL.'" fill="'.self::INK.'"/>'
            .$this->line($hinge, 0, $hinge, -$clear, 10)
            .'<path d="M '.$this->number($hinge).' '.$this->number(-$clear).' A '.$this->number($clear).' '.$this->number($clear).' 0 0 1 '.$this->number($hinge + $clear).' 0" fill="none" stroke="'.self::INK.'" stroke-width="6"/>';
    }

    /**
     * A hatch: a solid sill line across the wall with a single pane, so it reads differently from a window.
     *
     * @param  array{from: float, width: float}  $opening
     */
    private function hatch(array $opening): string
    {
        ['from' => $from, 'width' => $width] = $opening;
        $to = $from + $width;

        return '<rect x="'.$this->number($from).'" y="0" width="'.$this->number($width).'" height="'.self::WALL.'" fill="#ffffff" stroke="'.self::INK.'" stroke-width="10"/>'
            .$this->line($from, self::WALL / 2, $to, self::WALL / 2, 14)
            .$this->dimensionLine($from, $to, -350);
    }

    /**
     * A step outside a door, a little wider than the door.
     *
     * @param  array{from: float, width: float}  $opening
     */
    private function step(array $opening): string
    {
        ['from' => $from, 'width' => $width] = $opening;
        $stepDepth = self::STEP['depth'] * 1000;
        $overhang = (self::STEP['width'] * 1000 - $width) / 2;

        return '<rect x="'.$this->number($from - $overhang).'" y="'.$this->number(-$stepDepth).'" width="'.$this->number($width + 2 * $overhang).'" height="'.$this->number($stepDepth).'" fill="#eeeeee" fill-opacity="0.6" stroke="'.self::INK.'" stroke-width="8"/>';
    }

    /**
     * Sizes of an opening, written upright outside its wall: "1800 / 1200" for a window, outside and clear size for a door.
     *
     * @param  array{type: string, from: float, width: float, height: float}  $opening
     */
    private function openingLabels(string $wall, array $opening, int $wallLength, int $length, int $depth): string
    {
        $isDoor = $opening['type'] === UnitConfiguration::DOOR;
        $along = $opening['from'] + $opening['width'] / 2;
        $out = $isDoor ? 1150 : ($opening['type'] === UnitConfiguration::HATCH ? 580 : 480);
        $lines = match ($opening['type']) {
            UnitConfiguration::DOOR => [$this->millimetres($opening['width']).' × '.$this->millimetres($opening['height']), __('vrij').' '.$this->millimetres(UnitConfiguration::DOOR_CLEAR['width'] * 1000).' × '.$this->millimetres(UnitConfiguration::DOOR_CLEAR['height'] * 1000)],
            UnitConfiguration::HATCH => [__('Luik'), $this->millimetres($opening['width']).' / '.$this->millimetres($opening['height'])],
            default => [$this->millimetres($opening['width']).' / '.$this->millimetres($opening['height'])],
        };

        [$x, $y, $rotate] = match ($wall) {
            'front' => [$along, $depth + $out, 0],
            'back' => [$length - $along, -$out, 0],
            'left' => [-$out, $along, -90],
            'right' => [$length + $out, $depth - $along, -90],
        };

        $svg = '';
        foreach ($lines as $index => $text) {
            $offset = ($index - (count($lines) - 1) / 2) * 190;
            [$lineX, $lineY] = $rotate === 0 ? [$x, $y + $offset] : [$x + $offset, $y];
            $svg .= $this->text($lineX, $lineY + 55, $text, $rotate === 0 ? '' : 'transform="rotate(-90 '.$this->number($lineX).' '.$this->number($lineY + 55).')"');
        }

        return $svg;
    }

    /**
     * Dashed lines where coupled units meet, with the number of each unit; unit 1 is at the front.
     *
     * @param  Configuration  $configuration
     */
    private function seams(array $configuration, int $length, int $depth): string
    {
        $modules = $configuration['modules'];
        if ($modules === 1) {
            return '';
        }

        $unitDepth = $depth / $modules;
        $svg = '';
        for ($module = 1; $module <= $modules; $module++) {
            if ($module < $modules) {
                $y = $depth - $module * $unitDepth;
                $svg .= '<line x1="'.self::WALL.'" y1="'.$this->number($y).'" x2="'.($length - self::WALL).'" y2="'.$this->number($y).'" stroke="#777777" stroke-width="8" stroke-dasharray="60 50"/>';
            }
            // Below the middle of the unit, where its ceiling lights hang.
            $svg .= $this->text($length / 2, $depth - ($module - 0.5) * $unitDepth + 455, 'Unit '.$module, 'fill="#777777"');
        }

        return $svg;
    }

    /**
     * The lights and the chosen interior of one floor, scaled from nominal metres to the real size in millimetres.
     *
     * @param  Configuration  $configuration
     */
    private function interior(array $configuration, string $level, int $length, int $depth): string
    {
        $size = UnitConfiguration::SIZES[$configuration['size']];
        $scaleX = $length / $size['length'];
        $scaleY = $depth / ($size['depth'] * $configuration['modules']);
        $layout = InteriorLayout::for($configuration, $level);

        $lights = '';
        foreach ($layout['lights'] as $light) {
            $lights .= $this->light($light['x'] * $scaleX, $light['y'] * $scaleY);
        }

        // Lights hang from the ceiling, so they come first and everything on the floor and walls can be grabbed.
        $svg = $lights;
        foreach ($layout['items'] as $item) {
            $rect = ['x1' => $item['x1'] * $scaleX, 'y1' => $item['y1'] * $scaleY, 'x2' => $item['x2'] * $scaleX, 'y2' => $item['y2'] * $scaleY];
            $symbol = match ($item['type']) {
                'toilet' => $this->toilet($item, $rect),
                'kitchen' => $this->kitchen($item, $rect),
                'airco' => $this->airco($item, $rect),
                'heater' => $this->heater($item, $rect),
                'socket', 'network' => $this->connection($item, $rect),
                'chair' => $this->chair($item, $rect),
                default => $this->furniture($item, $rect),
            };

            $centerX = ($rect['x1'] + $rect['x2']) / 2;
            $centerY = ($rect['y1'] + $rect['y2']) / 2;
            $label = __(UnitConfiguration::INTERIOR[$item['type']] ?? UnitConfiguration::FURNITURE[$item['type']] ?? UnitConfiguration::CONNECTIONS[$item['type']] ?? $item['type']);
            $svg .= '<g class="plan-item" data-item="'.e($item['id']).'" data-x="'.round(($item['x1'] + $item['x2']) / 2, 3).'" data-y="'.round(($item['y1'] + $item['y2']) / 2, 3).'"'
                .($item['tentative'] ? ' opacity="0.55"' : '').'>'
                .'<title>'.e($label.($item['tentative'] ? ' ('.__('optioneel').')' : '')).'</title>'
                // A hit area around small symbols, so they can be grabbed on a touch screen too.
                .'<rect x="'.$this->number(min($rect['x1'], $centerX - 200)).'" y="'.$this->number(min($rect['y1'], $centerY - 200)).'" width="'.$this->number(max($rect['x2'] - $rect['x1'], 400)).'" height="'.$this->number(max($rect['y2'] - $rect['y1'], 400)).'" fill="#ffffff" fill-opacity="0"/>'
                .$symbol
                .($item['tentative'] ? $this->text($centerX, $centerY + ($item['type'] === 'toilet' ? -200 : 50), __('optie'), 'font-size="110" font-style="italic"') : '')
                .'</g>';
        }

        return $svg;
    }

    /**
     * A heater low on its wall: a narrow box with fins.
     *
     * @param  array{wall: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function heater(array $item, array $rect): string
    {
        $svg = '<rect x="'.$this->number($rect['x1']).'" y="'.$this->number($rect['y1']).'" width="'.$this->number($rect['x2'] - $rect['x1']).'" height="'.$this->number($rect['y2'] - $rect['y1']).'" fill="#ffffff" stroke="'.self::INK.'" stroke-width="8"/>';
        $alongX = in_array($item['wall'], ['front', 'back'], true);
        $from = $alongX ? $rect['x1'] : $rect['y1'];
        $to = $alongX ? $rect['x2'] : $rect['y2'];

        for ($fin = $from + 100; $fin < $to - 50; $fin += 100) {
            $svg .= $alongX ? $this->line($fin, $rect['y1'], $fin, $rect['y2'], 5) : $this->line($rect['x1'], $fin, $rect['x2'], $fin, 5);
        }

        return $svg;
    }

    /**
     * A wall socket (a half circle against the wall with a line to it) or a network point (a small square marked
     * "UTP"), as on an electrical plan.
     *
     * @param  array{type: string, wall: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function connection(array $item, array $rect): string
    {
        $centerX = ($rect['x1'] + $rect['x2']) / 2;
        $centerY = ($rect['y1'] + $rect['y2']) / 2;
        // From the wall into the room.
        [$inX, $inY] = match ($item['wall']) {
            'back' => [0, 1], 'front' => [0, -1], 'left' => [1, 0], 'right' => [-1, 0],
        };
        $wallX = $centerX - $inX * ($rect['x2'] - $rect['x1']) / 2;
        $wallY = $centerY - $inY * ($rect['y2'] - $rect['y1']) / 2;
        $symbolX = $wallX + $inX * 160;
        $symbolY = $wallY + $inY * 160;
        $stroke = 'stroke="'.self::INK.'" stroke-width="8"';

        if ($item['type'] === 'network') {
            return $this->line($wallX, $wallY, $symbolX, $symbolY, 8)
                .'<rect x="'.$this->number($symbolX - 75).'" y="'.$this->number($symbolY - 75).'" width="150" height="150" fill="#ffffff" '.$stroke.'/>'
                .$this->text($symbolX, $symbolY + 28, 'UTP', 'font-size="70" font-weight="bold"');
        }

        $angle = rad2deg(atan2($inY, $inX));

        return $this->line($wallX, $wallY, $symbolX, $symbolY, 8)
            .'<path d="M -90 0 A 90 90 0 0 1 90 0 Z" transform="translate('.$this->number($symbolX).' '.$this->number($symbolY).') rotate('.$this->number($angle - 90).')" fill="#ffffff" '.$stroke.'/>'
            .$this->line($symbolX - 45 * $inY, $symbolY + 45 * $inX, $symbolX - 45 * $inY + $inX * 60, $symbolY + 45 * $inX + $inY * 60, 6)
            .$this->line($symbolX + 45 * $inY, $symbolY - 45 * $inX, $symbolX + 45 * $inY + $inX * 60, $symbolY - 45 * $inX + $inY * 60, 6);
    }

    /**
     * A chair: the seat with its backrest on the side away from the desk or wall it faces.
     *
     * @param  array{wall: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function chair(array $item, array $rect): string
    {
        $stroke = 'stroke="'.self::INK.'" stroke-width="8"';
        $inset = 60;
        $back = 110;
        [$x1, $y1, $x2, $y2] = [$rect['x1'] + $inset, $rect['y1'] + $inset, $rect['x2'] - $inset, $rect['y2'] - $inset];

        [$bx1, $by1, $bx2, $by2] = match ($item['wall']) {
            'back' => [$x1, $y2 - $back, $x2, $y2],
            'front' => [$x1, $y1, $x2, $y1 + $back],
            'left' => [$x2 - $back, $y1, $x2, $y2],
            'right' => [$x1, $y1, $x1 + $back, $y2],
        };

        return '<rect x="'.$this->number($x1).'" y="'.$this->number($y1).'" width="'.$this->number($x2 - $x1).'" height="'.$this->number($y2 - $y1).'" rx="60" fill="#ffffff" '.$stroke.'/>'
            .'<rect x="'.$this->number($bx1).'" y="'.$this->number($by1).'" width="'.$this->number($bx2 - $bx1).'" height="'.$this->number($by2 - $by1).'" rx="40" fill="'.self::WALL_FILL.'" '.$stroke.'/>';
    }

    /**
     * A desk, counter, worktop or cabinet: its outline with its name, a cabinet also with the usual diagonal.
     *
     * @param  array{type: string, wall: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function furniture(array $item, array $rect): string
    {
        $stroke = 'stroke="'.self::INK.'" stroke-width="8"';
        $centerX = ($rect['x1'] + $rect['x2']) / 2;
        $centerY = ($rect['y1'] + $rect['y2']) / 2;
        $isUpright = in_array($item['wall'], ['left', 'right'], true);
        $names = ['desk' => __('Bureau'), 'counter' => __('Balie'), 'worktop' => __('Werkblad'), 'cabinet' => __('Kast')];

        $svg = '<rect x="'.$this->number($rect['x1']).'" y="'.$this->number($rect['y1']).'" width="'.$this->number($rect['x2'] - $rect['x1']).'" height="'.$this->number($rect['y2'] - $rect['y1']).'" fill="'.($item['type'] === 'counter' ? '#eeeeee' : '#ffffff').'" '.$stroke.'/>';

        if ($item['type'] === 'cabinet') {
            $svg .= $this->line($rect['x1'], $rect['y1'], $rect['x2'], $rect['y2'], 5);
        }

        return $svg.$this->text($centerX, $centerY + 45, $names[$item['type']] ?? '', 'font-size="120"'.($isUpright ? ' transform="rotate(-90 '.$this->number($centerX).' '.$this->number($centerY + 45).')"' : ''));
    }

    /**
     * A ceiling light: a circle with a cross.
     */
    private function light(float $x, float $y, float $radius = 160): string
    {
        $arm = $radius * M_SQRT1_2;

        return '<circle cx="'.$this->number($x).'" cy="'.$this->number($y).'" r="'.$this->number($radius).'" fill="#ffffff" stroke="'.self::INK.'" stroke-width="8"/>'
            .$this->line($x - $arm, $y - $arm, $x + $arm, $y + $arm, 8)
            .$this->line($x - $arm, $y + $arm, $x + $arm, $y - $arm, 8);
    }

    /**
     * The toilet cubicle in its corner: partitions on the two sides facing the room, a door opening outwards towards
     * the middle of the unit, the toilet against the outside wall and a hand basin against the side wall.
     *
     * It is drawn in a frame with its origin in the corner, x along the outside wall and y along the side wall, both
     * pointing into the unit, which is then mirrored into place.
     *
     * @param  array{wall: string, side?: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function toilet(array $item, array $rect): string
    {
        $isLeft = ($item['side'] ?? 'left') === 'left';
        $isBack = $item['wall'] === 'back';
        $width = $rect['x2'] - $rect['x1'];
        $depth = $rect['y2'] - $rect['y1'];
        $transform = 'matrix('.($isLeft ? 1 : -1).' 0 0 '.($isBack ? 1 : -1).' '.$this->number($isLeft ? $rect['x1'] : $rect['x2']).' '.$this->number($isBack ? $rect['y1'] : $rect['y2']).')';

        $partition = 50;
        $doorFrom = 150;
        $doorTo = min($width - $partition, $doorFrom + 700);
        $leaf = $doorTo - $doorFrom;
        $stroke = 'stroke="'.self::INK.'" stroke-width="8"';

        $svg = '<rect width="'.$this->number($width).'" height="'.$this->number($depth).'" fill="#ffffff"/>'
            .'<rect x="'.$this->number($width - $partition).'" width="'.$partition.'" height="'.$this->number($depth).'" fill="'.self::WALL_FILL.'" '.$stroke.'/>'
            .'<rect y="'.$this->number($depth - $partition).'" width="'.$doorFrom.'" height="'.$partition.'" fill="'.self::WALL_FILL.'" '.$stroke.'/>'
            .'<rect x="'.$this->number($doorTo).'" y="'.$this->number($depth - $partition).'" width="'.$this->number($width - $partition - $doorTo).'" height="'.$partition.'" fill="'.self::WALL_FILL.'" '.$stroke.'/>'
            .$this->line($doorFrom, $depth, $doorFrom, $depth + $leaf, 10)
            .'<path d="M '.$doorFrom.' '.$this->number($depth + $leaf).' A '.$this->number($leaf).' '.$this->number($leaf).' 0 0 0 '.$this->number($doorTo).' '.$this->number($depth).'" fill="none" stroke="'.self::INK.'" stroke-width="6"/>'
            // Toilet: cistern against the outside wall and the bowl in front of it.
            .'<rect x="'.$this->number($width / 2 - 200).'" y="0" width="400" height="180" fill="#ffffff" '.$stroke.'/>'
            .'<ellipse cx="'.$this->number($width / 2).'" cy="430" rx="180" ry="250" fill="#ffffff" '.$stroke.'/>'
            // Hand basin against the side wall.
            .'<rect x="0" y="'.$this->number($depth - 650).'" width="220" height="350" rx="40" fill="#ffffff" '.$stroke.'/>'
            .'<circle cx="80" cy="'.$this->number($depth - 475).'" r="25" fill="none" '.$stroke.'/>';

        return '<g transform="'.$transform.'">'.$svg.'</g>'
            .$this->text(($rect['x1'] + $rect['x2']) / 2 + ($isLeft ? 120 : -120), ($rect['y1'] + $rect['y2']) / 2 + ($isBack ? 250 : -150), 'WC', 'font-weight="bold"');
    }

    /**
     * The kitchenette against its wall: the worktop with a sink at one end and a two-burner hob at the other, and its
     * name in the room in front of it.
     *
     * @param  array{wall: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function kitchen(array $item, array $rect): string
    {
        $stroke = 'stroke="'.self::INK.'" stroke-width="8"';
        $isBack = $item['wall'] === 'back';
        $middle = ($rect['y1'] + $rect['y2']) / 2;

        $svg = '<rect x="'.$this->number($rect['x1']).'" y="'.$this->number($rect['y1']).'" width="'.$this->number($rect['x2'] - $rect['x1']).'" height="'.$this->number($rect['y2'] - $rect['y1']).'" fill="#ffffff" '.$stroke.'/>'
            .'<rect x="'.$this->number($rect['x1'] + 150).'" y="'.$this->number($middle - 180).'" width="500" height="360" rx="50" fill="#ffffff" '.$stroke.'/>'
            .'<circle cx="'.$this->number($rect['x1'] + 400).'" cy="'.$this->number($middle).'" r="30" fill="none" '.$stroke.'/>';

        foreach ([$rect['x2'] - 520, $rect['x2'] - 250] as $burner) {
            $svg .= '<circle cx="'.$this->number($burner).'" cy="'.$this->number($middle).'" r="100" fill="none" '.$stroke.'/>';
        }

        $labelY = $isBack ? $rect['y2'] + 230 : $rect['y1'] - 130;

        return $svg.$this->text(($rect['x1'] + $rect['x2']) / 2, $labelY, __('Keuken'), 'font-weight="bold"');
    }

    /**
     * An airco hanging high on its wall: dashed, as everything above the cutting height of a floor plan.
     *
     * @param  array{wall: string}  $item
     * @param  array{x1: float, y1: float, x2: float, y2: float}  $rect
     */
    private function airco(array $item, array $rect): string
    {
        $centerX = ($rect['x1'] + $rect['x2']) / 2;
        $centerY = ($rect['y1'] + $rect['y2']) / 2;
        $rotate = in_array($item['wall'], ['left', 'right'], true) ? ' transform="rotate(-90 '.$this->number($centerX).' '.$this->number($centerY).')"' : '';

        return '<rect x="'.$this->number($rect['x1']).'" y="'.$this->number($rect['y1']).'" width="'.$this->number($rect['x2'] - $rect['x1']).'" height="'.$this->number($rect['y2'] - $rect['y1']).'" fill="#ffffff" stroke="'.self::INK.'" stroke-width="8" stroke-dasharray="40 30"/>'
            .'<text x="'.$this->number($centerX).'" y="'.$this->number($centerY + 42).'" font-family="'.self::FONT.'" font-size="120" font-weight="bold" text-anchor="middle" fill="'.self::INK.'"'.$rotate.'>AC</text>';
    }

    /**
     * What the symbols mean, from the bottom left corner: the lights always, the airco, heater, wall sockets and network
     * points when there are any, followed by the note that the drawing is indicative and in millimetres. Entries wrap
     * onto a new row when the drawing is too narrow.
     *
     * @param  Configuration  $configuration
     * @return array{string, int} The legend and the height it takes beyond its first row.
     */
    private function legend(array $configuration, int $width, float $baseline): array
    {
        $interior = $configuration['interior'] ?? [];
        $connections = $configuration['connections'] ?? [];
        $dashed = 'fill="#ffffff" stroke="'.self::INK.'" stroke-width="8"';

        $entries = [[fn (float $x, float $y): string => $this->light($x + 80, $y - 45, 80), __('Verlichting')]];
        if (in_array('airco', $interior, true)) {
            $entries[] = [fn (float $x, float $y): string => '<rect x="'.$this->number($x).'" y="'.$this->number($y - 110).'" width="260" height="130" '.$dashed.' stroke-dasharray="40 30"/>'
                .$this->text($x + 130, $y - 10, 'AC', 'font-size="90" font-weight="bold"'), __('Airco (aan de wand, hoog)')];
        }
        if (in_array('heater', $interior, true)) {
            $entries[] = [fn (float $x, float $y): string => $this->heater(['wall' => 'back'], ['x1' => $x, 'y1' => $y - 90, 'x2' => $x + 260, 'y2' => $y - 10]), __('Verwarming (aan de wand, laag)')];
        }
        if (isset($connections['socket'])) {
            $entries[] = [fn (float $x, float $y): string => $this->connection(['type' => 'socket', 'wall' => 'left'], ['x1' => $x, 'y1' => $y - 60, 'x2' => $x, 'y2' => $y - 30]), __('Wandcontactdoos')];
        }
        if (isset($connections['network'])) {
            $entries[] = [fn (float $x, float $y): string => $this->connection(['type' => 'network', 'wall' => 'left'], ['x1' => $x, 'y1' => $y - 60, 'x2' => $x, 'y2' => $y - 30]), __('Netwerkaansluiting (UTP)')];
        }
        $entries[] = [null, __('Indicatieve tekening')];
        $entries[] = [null, __('Maten in mm')];

        $rowHeight = 260;
        $x = 200;
        $y = $baseline;
        $svg = '';

        foreach ($entries as [$symbol, $label]) {
            $symbolWidth = $symbol ? 340 : 0;
            $entryWidth = $symbolWidth + (int) ceil(mb_strlen($label) * 130 * 0.56) + 220;

            if ($x > 200 && $x + $entryWidth > $width - 200) {
                $x = 200;
                $y += $rowHeight;
            }

            $svg .= ($symbol ? $symbol($x, $y) : '').$this->text($x + $symbolWidth, $y, $label, 'font-size="130" text-anchor="start" fill="#555555"');
            $x += $entryWidth;
        }

        return [$svg, (int) ($y - $baseline)];
    }

    /**
     * Overall length above the plan and overall depth to the left of it.
     */
    private function overallDimensions(int $length, int $depth): string
    {
        $offset = 1050;

        return $this->line(0, -150, 0, -$offset - 100, 5).$this->line($length, -150, $length, -$offset - 100, 5)
            .'<g>'.$this->dimensionLine(0, $length, -$offset).'</g>'
            .$this->text($length / 2, -$offset - 60, (string) $length)
            .$this->line(-150, 0, -$offset - 100, 0, 5).$this->line(-150, $depth, -$offset - 100, $depth, 5)
            .'<g transform="matrix(0 1 1 0 0 0)">'.$this->dimensionLine(0, $depth, -$offset).'</g>'
            .$this->text(-$offset - 60, $depth / 2, (string) $depth, 'transform="rotate(-90 '.$this->number(-$offset - 60).' '.$this->number($depth / 2).')"');
    }

    /**
     * A dimension line along x at height y, with the slashed ticks of an architectural drawing at both ends.
     */
    private function dimensionLine(float $from, float $to, float $y): string
    {
        $tick = fn (float $x): string => $this->line($x - 60, $y + 60, $x + 60, $y - 60, 8);

        return $this->line($from - 120, $y, $to + 120, $y, 5).$tick($from).$tick($to);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, int $width): string
    {
        return '<line x1="'.$this->number($x1).'" y1="'.$this->number($y1).'" x2="'.$this->number($x2).'" y2="'.$this->number($y2).'" stroke="'.self::INK.'" stroke-width="'.$width.'"/>';
    }

    private function text(float $x, float $y, string $text, string $attributes = ''): string
    {
        $defaults = str_contains($attributes, 'text-anchor') ? '' : ' text-anchor="middle"';
        $fill = str_contains($attributes, 'fill=') ? '' : ' fill="'.self::INK.'"';
        $size = str_contains($attributes, 'font-size') ? '' : ' font-size="'.self::TEXT_SIZE.'"';

        return '<text x="'.$this->number($x).'" y="'.$this->number($y).'" font-family="'.self::FONT.'"'.$size.$defaults.$fill.($attributes !== '' ? ' '.$attributes : '').'>'.e($text).'</text>';
    }

    private function millimetres(float $value): string
    {
        return (string) (int) round($value);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
