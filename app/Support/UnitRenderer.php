<?php

namespace App\Support;

/**
 * Draws a unit configuration as an isometric SVG on a simple backdrop: one view of the front and
 * right walls, one of the back and left walls.
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
    public const BACKDROPS = [
        'industrial' => ['sky' => '#c3cfd8', 'horizon' => '#e4e9ec', 'ground' => '#56595c', 'groundFar' => '#6d7073'],
        'meadow' => ['sky' => '#bcdcf0', 'horizon' => '#eef6fb', 'ground' => '#7fb35c', 'groundFar' => '#9cc77b'],
        'site' => ['sky' => '#d3dbe2', 'horizon' => '#f1f3f5', 'ground' => '#a9a296', 'groundFar' => '#c2bcb1'],
        'plain' => ['sky' => '#f3f3f1', 'horizon' => '#f3f3f1', 'ground' => '#e2e1dc', 'groundFar' => '#e9e8e4'],
    ];

    /** @var array<string, array{front: string, side: string, label: string}> */
    public const VIEWS = [
        'front' => ['front' => 'front', 'side' => 'right', 'label' => 'Voorzijde en rechterzijde'],
        'back' => ['front' => 'back', 'side' => 'left', 'label' => 'Achterzijde en linkerzijde'],
    ];

    /**
     * Both views side by side with a caption under each, as shown in the admin.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function render(array $configuration): string
    {
        return $this->compose($configuration, array_keys(self::VIEWS), true);
    }

    /**
     * A single view in the 3:2 format the website uses for unit photos.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function renderView(array $configuration, string $view): string
    {
        return $this->compose($configuration, [$view], false);
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<string>  $views
     */
    private function compose(array $configuration, array $views, bool $captions): string
    {
        $configuration = UnitConfiguration::normalize($configuration);
        ['length' => $length, 'depth' => $depth] = UnitConfiguration::dimensions($configuration);

        // Single units share one canvas size, so they are drawn at the same scale; coupled units get more room.
        $largest = collect(UnitConfiguration::SIZES)->max(fn (array $size): int => $size['length'] + $size['depth']);
        $extent = max($largest, $length + $depth);
        $viewWidth = $extent * self::COS * self::SCALE + 2 * self::PADDING;
        $viewHeight = ($extent / 2 + self::HEIGHT) * self::SCALE + 2 * self::PADDING;
        $height = round($viewHeight + ($captions ? self::LABEL_HEIGHT : 0));

        if (count($views) === 1) {
            $viewWidth = max($viewWidth, $height * 1.5);
        }

        $width = round(count($views) * $viewWidth);
        $originY = $viewHeight / 2 + (self::HEIGHT / 2 + ($depth - $length) / 4) * self::SCALE;
        $horizonY = $originY - ($depth / 2) * self::SCALE - 14;

        $body = $this->backdrop($configuration['background'], $width, $height, $horizonY);

        foreach (array_values($views) as $index => $key) {
            $view = self::VIEWS[$key];
            $originX = $index * $viewWidth + ($viewWidth - ($length + $depth) * self::COS * self::SCALE) / 2;
            $body .= '<g transform="translate('.$this->number($originX).' '.$this->number($originY).')">'
                .$this->unit($length, $depth, $configuration['walls'][$view['front']], $configuration['walls'][$view['side']], $configuration['colors'], $this->seams($configuration))
                .'</g>';

            if ($captions) {
                $body .= '<text x="'.$this->number($index * $viewWidth + $viewWidth / 2).'" y="'.$this->number($height - 12).'" text-anchor="middle" font-family="Work Sans, Arial, sans-serif" font-size="13" fill="#4a4a4a">'.e($view['label']).'</text>';
            }
        }

        $label = UnitConfiguration::label($configuration);

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
     * @param  list<int>  $seams  Positions along the side face where two coupled units meet.
     */
    private function unit(int $length, int $depth, array $frontSlots, array $sideSlots, array $colors, array $seams): string
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
            .$this->face($side, $depth, $sideSlots, $this->shade($colors['cladding'], 0.78), $this->shade($colors['frame'], 0.85), '#9fbfd2', $seams)
            .$this->face($front, $length, $frontSlots, $colors['cladding'], $colors['frame'], '#c4def0')
            .$roof;
    }

    /**
     * @param  \Closure(float, float): array{float, float}  $point
     * @param  list<string>  $slots
     * @param  list<int>  $seams
     */
    private function face(\Closure $point, int $width, array $slots, string $cladding, string $frame, string $glass, array $seams = []): string
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

        foreach ($seams as $seam) {
            $svg .= $this->polygon($rect($seam - 0.1, $seam + 0.1, 0, $height), $frame);
        }

        return $svg
            .$this->polygon($rect(0, 0.12, 0, $height), $frame)
            .$this->polygon($rect($width - 0.12, $width, 0, $height), $frame)
            .$this->polygon($rect(0, $width, $height - 0.2, $height), $frame)
            .$this->polygon($rect(0, $width, 0, 0.15), $frame);
    }

    /**
     * Where coupled units meet, in metres from either end of a side wall.
     *
     * @param  Configuration  $configuration
     * @return list<int>
     */
    private function seams(array $configuration): array
    {
        $depth = UnitConfiguration::SIZES[$configuration['size']]['depth'];

        if ($configuration['modules'] === 1) {
            return [];
        }

        return array_map(fn (int $module): int => $module * $depth, range(1, $configuration['modules'] - 1));
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
