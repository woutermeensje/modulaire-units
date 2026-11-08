<?php

namespace App\Support;

/**
 * Draws a unit configuration as a technical floor plan seen from above: white background, black lines, walls in grey,
 * windows and doors in their openings and all dimensions in millimetres. The front wall is at the bottom, as in the
 * configurator. With two layers both floors are drawn side by side.
 *
 * The interior is drawn with the usual symbols: ceiling lights (always fitted) as a crossed circle, the toilet cubicle,
 * the kitchenette and every airco, which hangs high on the wall and is therefore dashed. See InteriorLayout.
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
        $height = $top + $depth + self::MARGIN;

        $body = '';
        foreach (array_keys($levels) as $index => $level) {
            $originX = $index * $planWidth + self::MARGIN;
            if ($titled) {
                $body .= $this->text($originX + $length / 2, 320, $levels[$level], 'font-size="220" font-weight="bold"');
            }
            $body .= '<g transform="translate('.$originX.' '.$top.')">'.$this->plan($configuration, $level, $length, $depth).'</g>';
        }

        $label = UnitConfiguration::label($configuration);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.e($label).'">'
            .'<title>'.e($label).'</title>'
            .'<rect width="'.$width.'" height="'.$height.'" fill="#ffffff"/>'
            .$body
            .$this->legend($height - 200, in_array('airco', $configuration['interior'] ?? [], true))
            .$this->text($width - 200, $height - 200, __('Maten in mm'), 'font-size="130" text-anchor="end" fill="#555555"')
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

            $svg .= $this->wall($wall, $wallLength, $length, $depth, $openings);
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
     */
    private function wall(string $wall, int $wallLength, int $length, int $depth, array $openings): string
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
            $svg .= $opening['type'] === UnitConfiguration::DOOR ? $this->door($opening) : $this->window($opening);
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
     * Sizes of an opening, written upright outside its wall: "1800 / 1200" for a window, outside and clear size for a door.
     *
     * @param  array{type: string, from: float, width: float, height: float}  $opening
     */
    private function openingLabels(string $wall, array $opening, int $wallLength, int $length, int $depth): string
    {
        $isDoor = $opening['type'] === UnitConfiguration::DOOR;
        $along = $opening['from'] + $opening['width'] / 2;
        $out = $isDoor ? 1150 : 480;
        $lines = $isDoor
            ? [$this->millimetres($opening['width']).' × '.$this->millimetres($opening['height']), 'vrij '.$this->millimetres(UnitConfiguration::DOOR_CLEAR['width'] * 1000).' × '.$this->millimetres(UnitConfiguration::DOOR_CLEAR['height'] * 1000)]
            : [$this->millimetres($opening['width']).' / '.$this->millimetres($opening['height'])];

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

        $svg = '';
        foreach ($layout['items'] as $item) {
            $rect = ['x1' => $item['x1'] * $scaleX, 'y1' => $item['y1'] * $scaleY, 'x2' => $item['x2'] * $scaleX, 'y2' => $item['y2'] * $scaleY];
            $svg .= match ($item['type']) {
                'toilet' => $this->toilet($item, $rect),
                'kitchen' => $this->kitchen($item, $rect),
                'airco' => $this->airco($item, $rect),
            };
        }

        foreach ($layout['lights'] as $light) {
            $svg .= $this->light($light['x'] * $scaleX, $light['y'] * $scaleY);
        }

        return $svg;
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
     * What the symbols mean, in the bottom left corner: the lights always, the airco when there is one.
     */
    private function legend(float $baseline, bool $hasAirco): string
    {
        $svg = $this->light(280, $baseline - 45, 80).$this->text(420, $baseline, __('Verlichting'), 'font-size="130" text-anchor="start" fill="#555555"');

        if ($hasAirco) {
            $svg .= '<rect x="1250" y="'.$this->number($baseline - 110).'" width="260" height="130" fill="#ffffff" stroke="'.self::INK.'" stroke-width="8" stroke-dasharray="40 30"/>'
                .$this->text(1380, $baseline - 10, 'AC', 'font-size="90" font-weight="bold"')
                .$this->text(1570, $baseline, __('Airco (aan de wand, hoog)'), 'font-size="130" text-anchor="start" fill="#555555"');
        }

        return $svg;
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
