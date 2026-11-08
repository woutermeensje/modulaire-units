<?php

namespace App\Support;

/**
 * A unit a visitor put together in the unit composer, as an item of "Mijn offerte". It links back to the composer
 * with exactly this composition, so whoever handles the quote sees what was asked for.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
final class ComposedUnit
{
    public const TYPE = 'samenstelling';

    /**
     * @param  Configuration  $configuration
     */
    public function __construct(public readonly array $configuration) {}

    /**
     * Identifies the composition in the quote, so adding the same composition again raises its quantity.
     */
    public function key(): string
    {
        return substr(md5((string) json_encode($this->configuration)), 0, 12);
    }

    public function title(): string
    {
        return __('Eigen samenstelling: :label', ['label' => UnitConfiguration::label($this->configuration)]);
    }

    /**
     * Colours, the number of windows and doors, the interior and the use in the language of the page, e.g.
     * "Wanden antraciet, frame antraciet, 2 ramen, 1 deur, inrichting: airco, keukentje, gebruik: kantoor, kantine".
     */
    public function summary(): string
    {
        $windows = 0;
        $doors = 0;

        foreach (array_keys(UnitConfiguration::LEVELS) as $level) {
            foreach ($this->configuration[$level] ?? [] as $wall => $slots) {
                $openings = UnitConfiguration::openings($slots, UnitConfiguration::slotCount($this->configuration['size'], $wall));
                $windows += count(array_filter($openings, fn (array $opening): bool => $opening['type'] === UnitConfiguration::WINDOW));
                $doors += count(array_filter($openings, fn (array $opening): bool => $opening['type'] === UnitConfiguration::DOOR));
            }
        }

        $names = fn (array $keys, array $labels): string => mb_strtolower(implode(', ', array_map(fn (string $key): string => __($labels[$key]), $keys)));
        $colour = fn (string $part): string => mb_strtolower(__(UnitConfiguration::COLORS[$this->configuration['colors'][$part]]));

        return implode(', ', array_filter([
            __('Wanden :colour', ['colour' => $colour('cladding')]),
            __('frame :colour', ['colour' => $colour('frame')]),
            trans_choice(':count raam|:count ramen', $windows),
            trans_choice(':count deur|:count deuren', $doors),
            $this->configuration['stairs'] ? __('buitentrap') : null,
            empty($this->configuration['interior']) ? null : __('inrichting: :items', ['items' => $names($this->configuration['interior'], UnitConfiguration::INTERIOR)]),
            empty($this->configuration['usage']) ? null : __('gebruik: :items', ['items' => $names($this->configuration['usage'], UnitConfiguration::USAGES)]),
        ]));
    }

    /**
     * A drawing of the front and right walls as an SVG data URI, so it can stand in for a unit photo in an <img>.
     */
    public function image(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(app(UnitRenderer::class)->renderView($this->configuration, 'front'));
    }

    /**
     * The public unit composer, opened with this composition.
     */
    public function url(): string
    {
        return route('unit-composer.public', [UnitConfiguration::SHARE_KEY => UnitConfiguration::toShareCode($this->configuration)], false);
    }
}
