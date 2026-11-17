<?php

namespace App\Support;

/**
 * The list of everything in a composed unit, as it stands in the quote PDF for the client: the unit itself, the
 * windows and doors, and every item that was ticked in the quote composer. Items still marked as uncertain get
 * "(optioneel)". Written in the active language; see QuotePdf.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
final class QuoteSpecification
{
    /**
     * @param  Configuration  $configuration
     */
    public function __construct(private readonly array $configuration) {}

    /**
     * Sections with their lines, in the order of the PDF; empty sections are left out.
     *
     * @return array<string, list<string>>
     */
    public function sections(): array
    {
        $configuration = $this->configuration;
        $outer = UnitConfiguration::outerSize($configuration);
        $openings = (new ComposedUnit($configuration))->openingCounts();
        $openingLabels = [
            UnitConfiguration::WINDOW => ':count× raam (180 x 120 cm)',
            UnitConfiguration::SINGLE_WINDOW => ':count× enkel raam (94,5 x 120 cm)',
            UnitConfiguration::DOOR => ':count× buitendeur (87,5 x 212,5 cm)',
            UnitConfiguration::HATCH => ':count× uitgifteluik (80 cm, wind- en waterdicht)',
        ];
        $metres = fn (int $millimetres): string => number_format($millimetres / 1000, 2, app()->getLocale() === 'nl' ? ',' : '.', '');

        $sections = [
            __('Unit') => array_values(array_filter([
                UnitConfiguration::label($configuration),
                __('Buitenmaat :length x :depth meter', ['length' => $metres($outer['length']), 'depth' => $metres($outer['depth'])])
                    .($configuration['floors'] > 1 ? ', '.trans_choice('{1} 1 laag|[2,*] :count lagen', $configuration['floors']) : ''),
                __('Wanden en frame :colour', ['colour' => mb_strtolower(__(UnitConfiguration::COLORS[$configuration['colors']['cladding']]))]),
                __('Kozijnen van ramen en deuren wit (RAL 9010)'),
                ($configuration['floors'] > 1 && $configuration['stairs']) ? __('Buitentrap met bordes naar de deuren op de verdieping') : null,
            ])),
            __('Ramen en deuren') => array_values(array_filter(array_map(
                fn (string $type): ?string => $openings[$type] > 0 ? __($openingLabels[$type], ['count' => $openings[$type]]) : null,
                array_keys($openingLabels),
            ))),
            __('Toegang') => ($configuration['steps'] ?? false) ? [__('Opstapje voor de deuren op de begane grond')] : [],
            __('Klimaat, sanitair en keuken') => array_map(fn (string $item): string => $this->name($item, UnitConfiguration::INTERIOR), $configuration['interior'] ?? []),
            __('Elektra en netwerk') => $this->amounts($configuration['connections'] ?? [], UnitConfiguration::CONNECTIONS),
            __('Meubilair') => $this->amounts($configuration['furniture'] ?? [], UnitConfiguration::FURNITURE),
            __('Type gebruik') => empty($configuration['usage']) ? [] : [implode(', ', array_map(fn (string $usage): string => __(UnitConfiguration::USAGES[$usage]), $configuration['usage']))],
        ];

        return array_filter($sections);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function name(string $key, array $labels): string
    {
        return __($labels[$key]).(in_array($key, $this->configuration['tentative'] ?? [], true) ? ' ('.__('optioneel').')' : '');
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    private function amounts(array $counts, array $labels): array
    {
        // Only the first letter becomes lower case, so abbreviations such as "UTP" stay as they are.
        return array_map(fn (string $key, int $count): string => $count.'× '.mb_lcfirst($this->name($key, $labels)), array_keys($counts), array_values($counts));
    }
}
