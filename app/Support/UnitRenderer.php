<?php

namespace App\Support;

/**
 * Draws a unit configuration as an isometric SVG: one view of the front and right walls,
 * one of the back and left walls, side by side on a simple backdrop.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
class UnitRenderer
{
    /** Pixels per metre. */
    private const SCALE = 40;

    /** Wall height in metres. */
    private const HEIGHT = 2.6;

    private const COS = 0.8660254;

    private const PADDING = 36;

    private const LABEL_HEIGHT = 28;

    /** @var array<string, array{sky: string, horizon: string, ground: string, groundFar: string}> */
    private const BACKDROPS = [
        'meadow' => ['sky' => '#bcdcf0', 'horizon' => '#eef6fb', 'ground' => '#7fb35c', 'groundFar' => '#9cc77b'],
        'site' => ['sky' => '#d3dbe2', 'horizon' => '#f1f3f5', 'ground' => '#a9a296', 'groundFar' => '#c2bcb1'],
        'plain' => ['sky' => '#f3f3f1', 'horizon' => '#f3f3f1', 'ground' => '#e2e1dc', 'groundFar' => '#e9e8e4'],
    ];

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function render(array $configuration): string
    {
        $configuration = UnitConfiguration::normalize($configuration);
        $size = UnitConfiguration::SIZES[$configuration['size']];
        $length = $size['length'];
        $depth = $size['depth'];

        $largest = collect(UnitConfiguration::SIZES)->sortByDesc(fn (array $size): int => $size['length'] + $size['depth'])->first();
        $viewWidth = ($largest['length'] + $largest['depth']) * self::COS * self::SCALE + 2 * self::PADDING;
        $viewHeight = (($largest['length'] + $largest['depth']) / 2 + self::HEIGHT) * self::SCALE + 2 * self::PADDING;
        $width = round(2 * $viewWidth);
        $height = round($viewHeight + self::LABEL_HEIGHT);

        $views = [
            ['front' => 'front', 'side' => 'right', 'label' => 'Voorzijde en rechterzijde'],
            ['front' => 'back', 'side' => 'left', 'label' => 'Achterzijde en linkerzijde'],
        ];

        $originY = $viewHeight / 2 + (self::HEIGHT / 2 + ($depth - $length) / 4) * self::SCALE;
        $horizonY = $originY - ($depth / 2) * self::SCALE - 14;

        $body = $this->backdrop($configuration['background'], $width, $height, $horizonY);

        foreach ($views as $index => $view) {
            $originX = $index * $viewWidth + ($viewWidth - ($length + $depth) * self::COS * self::SCALE) / 2;
            $body .= '<g transform="translate('.$this->number($originX).' '.$this->number($originY).')">'
                .$this->unit($length, $depth, $configuration['walls'][$view['front']], $configuration['walls'][$view['side']], $configuration['colors'])
                .'</g>'
                .'<text x="'.$this->number($index * $viewWidth + $viewWidth / 2).'" y="'.$this->number($height - 12).'" text-anchor="middle" font-family="Work Sans, Arial, sans-serif" font-size="13" fill="#4a4a4a">'.e($view['label']).'</text>';
        }

        $label = 'Modulaire unit van '.$size['label'];

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" width="'.$width.'" height="'.$height.'" role="img" aria-label="'.e($label).'">'
            .'<title>'.e($label).'</title>'.$body.'</svg>';
    }

    private function backdrop(string $background, float $width, float $height, float $horizonY): string
    {
        $colors = self::BACKDROPS[$background];
        $id = 'unit-bg-'.$background;

        return '<defs>'
            .'<linearGradient id="'.$id.'-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$colors['sky'].'"/><stop offset="1" stop-color="'.$colors['horizon'].'"/></linearGradient>'
            .'<linearGradient id="'.$id.'-ground" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$colors['groundFar'].'"/><stop offset="1" stop-color="'.$colors['ground'].'"/></linearGradient>'
            .'</defs>'
            .'<rect width="'.$width.'" height="'.$this->number($horizonY).'" fill="url(#'.$id.'-sky)"/>'
            .'<rect y="'.$this->number($horizonY).'" width="'.$width.'" height="'.$this->number($height - $horizonY).'" fill="url(#'.$id.'-ground)"/>';
    }

    /**
     * One isometric view: the "front" face runs along x at y = 0, the "side" face along y at x = length.
     *
     * @param  list<string>  $frontSlots
     * @param  list<string>  $sideSlots
     * @param  array{cladding: string, frame: string}  $colors
     */
    private function unit(int $length, int $depth, array $frontSlots, array $sideSlots, array $colors): string
    {
        $front = fn (float $s, float $z): array => $this->project($s, 0, $z);
        $side = fn (float $s, float $z): array => $this->project($length, $s, $z);
        $height = self::HEIGHT;

        $shadow = $this->polygon([
            $this->project(-0.1, -0.1, 0), $this->project($length + 0.35, -0.1, 0),
            $this->project($length + 0.35, $depth + 0.2, 0), $this->project(-0.1, $depth + 0.2, 0),
        ], '#000000', 'fill-opacity="0.16"');

        $roof = $this->polygon([
            $this->project(0, 0, $height), $this->project($length, 0, $height),
            $this->project($length, $depth, $height), $this->project(0, $depth, $height),
        ], $this->shade($colors['frame'], 1.35));

        return $shadow
            .$this->face($side, $depth, $sideSlots, $this->shade($colors['cladding'], 0.78), $this->shade($colors['frame'], 0.85), '#9fbfd2')
            .$this->face($front, $length, $frontSlots, $colors['cladding'], $colors['frame'], '#c4def0')
            .$roof;
    }

    /**
     * @param  \Closure(float, float): array{float, float}  $point
     * @param  list<string>  $slots
     */
    private function face(\Closure $point, int $width, array $slots, string $cladding, string $frame, string $glass): string
    {
        $height = self::HEIGHT;
        $rect = fn (float $s1, float $s2, float $z1, float $z2): array => [$point($s1, $z1), $point($s2, $z1), $point($s2, $z2), $point($s1, $z2)];

        $svg = $this->polygon($rect(0, $width, 0, $height), $cladding);

        for ($rib = 0.5; $rib < $width; $rib += 0.5) {
            [$x1, $y1] = $point($rib, 0.15);
            [$x2, $y2] = $point($rib, $height - 0.2);
            $svg .= '<line x1="'.$this->number($x1).'" y1="'.$this->number($y1).'" x2="'.$this->number($x2).'" y2="'.$this->number($y2).'" stroke="'.$this->shade($cladding, 0.86).'" stroke-width="1"/>';
        }

        foreach ($slots as $index => $slot) {
            $svg .= match ($slot) {
                UnitConfiguration::WINDOW => $this->polygon($rect($index + 0.15, $index + 0.85, 0.9, 2.1), $glass, 'stroke="'.$frame.'" stroke-width="3" stroke-linejoin="round"')
                    .$this->polygon($rect($index + 0.48, $index + 0.52, 0.9, 2.1), $frame),
                UnitConfiguration::DOOR => $this->polygon($rect($index + 0.12, $index + 0.88, 0.15, 2.2), $this->shade($frame, 1.25), 'stroke="'.$frame.'" stroke-width="3" stroke-linejoin="round"')
                    .$this->polygon($rect($index + 0.7, $index + 0.78, 1.0, 1.08), '#d9d9d9'),
                default => '',
            };
        }

        return $svg
            .$this->polygon($rect(0, 0.12, 0, $height), $frame)
            .$this->polygon($rect($width - 0.12, $width, 0, $height), $frame)
            .$this->polygon($rect(0, $width, $height - 0.2, $height), $frame)
            .$this->polygon($rect(0, $width, 0, 0.15), $frame);
    }

    /**
     * Isometric projection of a point in metres to SVG coordinates relative to the view origin.
     *
     * @return array{float, float}
     */
    private function project(float $x, float $y, float $z): array
    {
        return [($x + $y) * self::COS * self::SCALE, (($x - $y) / 2 - $z) * self::SCALE];
    }

    /**
     * @param  list<array{float, float}>  $points
     */
    private function polygon(array $points, string $fill, string $attributes = ''): string
    {
        $coordinates = implode(' ', array_map(fn (array $point): string => $this->number($point[0]).','.$this->number($point[1]), $points));

        return '<polygon points="'.$coordinates.'" fill="'.$fill.'"'.($attributes !== '' ? ' '.$attributes : '').'/>';
    }

    /**
     * Darken (factor < 1) or lighten (factor > 1) a hex colour.
     */
    private function shade(string $hex, float $factor): string
    {
        $channels = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $channels = array_map(
            fn (int $channel): int => (int) round($factor <= 1 ? $channel * $factor : $channel + (255 - $channel) * ($factor - 1)),
            $channels,
        );

        return sprintf('#%02x%02x%02x', ...array_map(fn (int $channel): int => max(0, min(255, $channel)), $channels));
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
