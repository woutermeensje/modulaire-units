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
        return 'Eigen samenstelling: '.UnitConfiguration::label($this->configuration);
    }

    /**
     * Colours, the number of windows and doors and the use, e.g. "Wanden antraciet, frame antraciet, 2 ramen, 1 deur, gebruik: kantoor, kantine".
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

        $colour = fn (string $part): string => mb_strtolower(UnitConfiguration::COLORS[$this->configuration['colors'][$part]]);

        return implode(', ', array_filter([
            'Wanden '.$colour('cladding'),
            'frame '.$colour('frame'),
            $windows === 1 ? '1 raam' : $windows.' ramen',
            $doors === 1 ? '1 deur' : $doors.' deuren',
            $this->configuration['stairs'] ? 'buitentrap' : null,
            empty($this->configuration['usage']) ? null : 'gebruik: '.mb_strtolower(implode(', ', array_map(fn (string $usage): string => UnitConfiguration::USAGES[$usage], $this->configuration['usage']))),
        ]));
    }

    /**
     * The public unit composer, opened with this composition.
     */
    public function url(): string
    {
        return route('unit-composer.public', [UnitConfiguration::SHARE_KEY => UnitConfiguration::toShareCode($this->configuration)], false);
    }
}
