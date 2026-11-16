<?php

namespace App\Support;

/**
 * The list of everything in a composed unit, as it stands in the quote PDF for the client: the unit itself, the
 * windows and doors, and every item that was ticked in the quote composer. Items still marked as uncertain get
 * "(optioneel)".
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

        $sections = [
            'Unit' => array_values(array_filter([
                UnitConfiguration::label($configuration),
                'Buitenmaat '.number_format($outer['length'] / 1000, 2, ',', '.').' x '.number_format($outer['depth'] / 1000, 2, ',', '.').' meter'
                    .($configuration['floors'] > 1 ? ', '.$configuration['floors'].' lagen' : ''),
                'Wanden en frame '.mb_strtolower(UnitConfiguration::COLORS[$configuration['colors']['cladding']]),
                'Kozijnen van ramen en deuren wit (RAL 9010)',
                ($configuration['floors'] > 1 && $configuration['stairs']) ? 'Buitentrap met bordes naar de deuren op de verdieping' : null,
            ])),
            'Ramen en deuren' => array_values(array_filter(array_map(
                fn (string $type): ?string => $openings[$type] > 0 ? str_replace(':count', (string) $openings[$type], $openingLabels[$type]) : null,
                array_keys($openingLabels),
            ))),
            'Toegang' => ($configuration['steps'] ?? false) ? ['Opstapje voor de deuren op de begane grond'] : [],
            'Klimaat, sanitair en keuken' => array_map(fn (string $item): string => $this->name($item, UnitConfiguration::INTERIOR), $configuration['interior'] ?? []),
            'Elektra en netwerk' => $this->amounts($configuration['connections'] ?? [], UnitConfiguration::CONNECTIONS),
            'Meubilair' => $this->amounts($configuration['furniture'] ?? [], UnitConfiguration::FURNITURE),
            'Type gebruik' => empty($configuration['usage']) ? [] : [implode(', ', array_map(fn (string $usage): string => UnitConfiguration::USAGES[$usage], $configuration['usage']))],
        ];

        return array_filter($sections);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function name(string $key, array $labels): string
    {
        return $labels[$key].(in_array($key, $this->configuration['tentative'] ?? [], true) ? ' (optioneel)' : '');
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    private function amounts(array $counts, array $labels): array
    {
        return array_map(fn (string $key, int $count): string => $count.'× '.mb_strtolower($this->name($key, $labels)), array_keys($counts), array_values($counts));
    }
}
