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
     * Colours, the number of windows, doors and hatches, the interior, furniture, connections and the use in the
     * language of the page, e.g. "Wanden antraciet, 4 ramen, 1 deur, 1 uitgifteluik, opstapje,
     * inrichting: airco, verwarming, sanitair (optioneel), meubilair: 1× bureau, 1× bureaustoel, elektra: 4×
     * wandcontactdoos, 1× netwerkaansluiting (utp), gebruik: portiersloge".
     */
    public function summary(): string
    {
        $openings = [UnitConfiguration::WINDOW => 0, UnitConfiguration::DOOR => 0, UnitConfiguration::HATCH => 0];

        foreach (array_keys(UnitConfiguration::LEVELS) as $level) {
            foreach ($this->configuration[$level] ?? [] as $wall => $slots) {
                foreach (UnitConfiguration::openings($slots, UnitConfiguration::slotCount($this->configuration['size'], $wall)) as $opening) {
                    $openings[$opening['type']]++;
                }
            }
        }

        $tentative = $this->configuration['tentative'] ?? [];
        $name = fn (string $key, array $labels): string => mb_strtolower(__($labels[$key])).(in_array($key, $tentative, true) ? ' ('.__('optioneel').')' : '');
        $names = fn (array $keys, array $labels): string => implode(', ', array_map(fn (string $key): string => $name($key, $labels), $keys));
        $amounts = fn (array $counts, array $labels): string => implode(', ', array_map(fn (string $key, int $count): string => $count.'× '.$name($key, $labels), array_keys($counts), $counts));
        $colour = fn (string $part): string => mb_strtolower(__(UnitConfiguration::COLORS[$this->configuration['colors'][$part]]));

        return implode(', ', array_filter([
            __('Wanden :colour', ['colour' => $colour('cladding')]),
            trans_choice(':count raam|:count ramen', $openings[UnitConfiguration::WINDOW]),
            trans_choice(':count deur|:count deuren', $openings[UnitConfiguration::DOOR]),
            $openings[UnitConfiguration::HATCH] > 0 ? trans_choice(':count uitgifteluik|:count uitgifteluiken', $openings[UnitConfiguration::HATCH]) : null,
            ($this->configuration['steps'] ?? false) ? __('opstapje') : null,
            $this->configuration['stairs'] ? __('buitentrap') : null,
            empty($this->configuration['interior']) ? null : __('inrichting: :items', ['items' => $names($this->configuration['interior'], UnitConfiguration::INTERIOR)]),
            empty($this->configuration['furniture']) ? null : __('meubilair: :items', ['items' => $amounts($this->configuration['furniture'], UnitConfiguration::FURNITURE)]),
            empty($this->configuration['connections']) ? null : __('elektra: :items', ['items' => $amounts($this->configuration['connections'], UnitConfiguration::CONNECTIONS)]),
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
