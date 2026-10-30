<?php

namespace App\Support;

/**
 * Draws a unit configuration as a technical floor plan seen from above: white background, black lines, walls in grey,
 * windows and doors in their openings and all dimensions in millimetres. The front wall is at the bottom, as in the
 * configurator. With two layers both floors are drawn side by side.
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
            .$this->text($width - 200, $height - 200, 'Maten in mm', 'font-size="130" text-anchor="end" fill="#555555"')
            .'</svg>';
    }

    /**
     * One floor: walls with their openings, seams between coupled units and the overall dimensions.
     *
     * @param  Configuration  $configuration
     */
    private function plan(array $configuration, string $level, int $length, int $depth): string
    {
        $svg = $this->seams($configuration, $length, $depth);
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
            $svg .= $this->text($length / 2, $depth - ($module - 0.5) * $unitDepth + 55, 'Unit '.$module, 'fill="#777777"');
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
