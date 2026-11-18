<?php

namespace App\Support;

/**
 * The list of everything in a quote, as it stands in the PDF for the client: the unit with its exact size, the windows
 * and doors, every item that was ticked in the quote composer and the technical specification the admin wrote per
 * section (one line per item). Items still marked as uncertain get "(optioneel)". Written in the active language; see
 * QuotePdf.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 */
final class QuoteSpecification
{
    /**
     * The technical sections an admin fills in per quote (see units.md), in the order of the form: key => the section of
     * the PDF the lines go in (translated with __()), the label in the form and an example as placeholder. Several keys
     * can share a section of the PDF; their lines are then listed together, after what the composer puts there.
     *
     * The examples are only a hint of the level of detail; what a new quote starts with is in STANDARD.
     *
     * @var array<string, array{section: string, label: string, placeholder: string}>
     */
    public const DETAILS = [
        'dimensions' => ['section' => 'Unit', 'label' => 'Maatvoering en oppervlakte', 'placeholder' => "Bijv. vrije binnenmaat … x … mm\nNetto vloeroppervlak ca. … m²"],
        'construction' => ['section' => 'Constructie', 'label' => 'Constructie (staalframe)', 'placeholder' => "Bijv. gelaste staalconstructie 3–4 mm\nStapelbaar volgens statische berekening\nGeen heftruckopeningen"],
        'exterior' => ['section' => 'Buitenafwerking', 'label' => 'Buitenafwerking en corrosiebescherming', 'placeholder' => "Bijv. gevel van verzinkte profielplaat 0,55 mm\nCorrosieklasse …, totale laagdikte … µm"],
        'insulation' => ['section' => 'Isolatie', 'label' => 'Isolatie (dak, wanden, vloer)', 'placeholder' => "Bijv. dak: 100 mm minerale wol A1, U < … W/m²K\nWanden: …\nVloer: …\nDampremmende PE-folie … mm"],
        'roof' => ['section' => 'Dak en hemelwaterafvoer', 'label' => 'Dak en hemelwaterafvoer', 'placeholder' => "Bijv. verzinkte trapeziumplaat 0,75 mm, geventileerd\nDakbelasting … kN/m²\n4 regenpijpen in de hoekstijlen"],
        'walls' => ['section' => 'Wanden en plafond', 'label' => 'Binnenwanden en plafond', 'placeholder' => "Bijv. witte melamine spaanplaat 12 mm\nGeen brandwerende afwerking"],
        'floor' => ['section' => 'Vloer', 'label' => 'Vloer', 'placeholder' => "Bijv. PVC-vloer 1,4 mm, kleur …\nCementgebonden spaanplaat 22 mm\nVloerbelasting … kN/m²"],
        'openings' => ['section' => 'Ramen en deuren', 'label' => 'Ramen en deuren (details)', 'placeholder' => "Bijv. ramen draai-kiep, dubbel glas Ug … W/m²K, met rolluik\nBuitendeur staal, cilinderslot met 3 sleutels"],
        'climate' => ['section' => 'Klimaat, sanitair en keuken', 'label' => 'Verwarming, ventilatie en airco', 'placeholder' => "Bijv. elektrische wandkachel 2 kW met thermostaat\n2 ventilatieroosters\nSplit-airco … kW koelen"],
        'plumbing' => ['section' => 'Klimaat, sanitair en keuken', 'label' => 'Water, sanitair en keuken', 'placeholder' => "Bijv. wateraansluiting via de vloer\nElektrische boiler … liter"],
        'electrical' => ['section' => 'Elektra en netwerk', 'label' => 'Elektra en verlichting (details)', 'placeholder' => "Bijv. installatie volgens Nederlandse norm\nAansluiting CEE 32A\n2 LED-armaturen 25 W, 4.000 K"],
        'coupling' => ['section' => 'Koppeling en accessoires', 'label' => 'Koppeling en accessoires', 'placeholder' => "Bijv. koppelmateriaal en rubberafdichtingen\nGeen trap of hellingbaan"],
        'site' => ['section' => 'Fundering en locatie', 'label' => 'Fundering en locatie (wat moet klaar zijn)', 'placeholder' => "Bijv. vlakke, draagkrachtige ondergrond (stelconplaten)\n150–300 mm vrije ruimte onder de unit\nStroomaansluiting 230 V binnen … meter"],
        'transport' => ['section' => 'Transport en plaatsing', 'label' => 'Transport en plaatsing', 'placeholder' => "Bijv. bereikbaar voor vrachtwagen met kraan\nPlaatsing en aansluiten door …"],
    ];

    /**
     * What a new quote starts with: the standard unit of the supplier Moravia (see moravia.md), as the client reads it.
     * Only what is the same for every unit of that supplier; what the composer already lists (windows, doors, sockets,
     * airco) is left out, and so are prices and the supplier's name. The admin changes it for another supplier or a
     * used unit. Sections without a standard (dimensions, water, coupling) start empty. Transport has the transporter's
     * rate; its diesel surcharge changes with the diesel price, so check it now and then.
     *
     * @var array<string, string>
     */
    public const STANDARD = [
        'construction' => "Gelaste staalconstructie van 3 tot 4 mm staal\nStapelbaar volgens statische berekening (berekening niet inbegrepen)\nZonder heftruckopeningen",
        'exterior' => "Gevel van verzinkte profielplaat 0,55 mm\nCorrosiebescherming klasse C3M: primer 70 µm en toplaag 50 µm, samen 120 µm\nVerwachte levensduur van de coating 7 tot 15 jaar",
        'insulation' => "Dak: 100 mm minerale wol A1, λ 0,035 W/mK, U < 0,48 W/m²K\nWanden: 100 mm minerale wol A1, λ 0,035 W/mK\nVloer: 100 mm minerale wol A1, λ 0,035 W/mK, U < 0,63 W/m²K\nDampscherm van PE-folie 0,2 mm\nIsolatieberekening niet inbegrepen",
        'roof' => "Verzinkte trapeziumplaat 0,75 mm, geventileerd\nDakbelasting 1,5 kN/m² (zwaarder uitvoeren kan tegen meerprijs, bijv. voor sneeuwbelasting)\nBakgoot en 4 regenpijpen Ø 63 mm, weggewerkt in de hoekstijlen\nAansluiten van de regenpijpen op de afvoer door de opdrachtgever",
        'walls' => "Wanden en plafond van witte melamine spaanplaat 12 mm, emissieklasse E1\nNaden afgewerkt met kunststof strips\nHouten binnenconstructie van 100 mm\nZonder brandwerende uitvoering",
        'floor' => "PVC-vloer 1,4 mm, grijs marmer, met kunststof plint\nCementgebonden spaanplaat 22 mm, brandklasse A2-s1,d0\nOnderzijde van verzinkte staalplaat 0,55 mm\nVloerbelasting 2,5 kN/m²",
        'openings' => "Ramen draai-kiep, wit kunststof kozijn, dubbel glas Ug 1,1 W/m²K\nWit kunststof buitenrolluik bij de ramen van 180 x 120 cm\nBuitendeur van verzinkt staal met stalen kozijn, binnenzijde wit (RAL 9003)\nVrije doorgang buitendeur 811 x 2.093 mm\nAluminium deurbeslag, cilinderslot met 3 sleutels",
        'climate' => "Elektrische wandkachel 2 kW met thermostaat en vorstbeveiliging\n2 ventilatieroosters in de wanden\nGeen mechanische ventilatie",
        'electrical' => "Elektrische installatie volgens de Nederlandse norm\nVerdeelkast met automaten en aardlekschakelaar\nAansluiting CEE 32A (in- en uitgaand)\n1 lichtschakelaar\n2 LED-armaturen 25 W, 3.150 lm, 4.000 K\nStopcontacten met kinderbeveiliging\nZonder overspanningsbeveiliging en zonder brandbeveiligingsschakelaar (AFDD)",
        'site' => "Vlakke, draagkrachtige fundering, klaar vóór de plaatsing\n150 tot 300 mm geventileerde ruimte onder de unit: rondom niet dichtmaken, wel gaas tegen dieren\nRegenpijpen aangesloten op de afvoer, zodat er geen water onder de unit blijft staan\nIn de winter verwarmd houden op minimaal 10 °C, tegen condens en bevriezing",
        'transport' => 'Transport op regiebasis: € 148,50 per uur + 12% dieseltoeslag = € 166,32 per uur (excl. btw)',
    ];

    /** The sections of the PDF in their order; see DETAILS for the ones the admin writes. */
    private const ORDER = [
        'Unit', 'Constructie', 'Buitenafwerking', 'Isolatie', 'Dak en hemelwaterafvoer', 'Wanden en plafond', 'Vloer',
        'Ramen en deuren', 'Toegang', 'Klimaat, sanitair en keuken', 'Elektra en netwerk', 'Meubilair',
        'Koppeling en accessoires', 'Fundering en locatie', 'Transport en plaatsing', 'Type gebruik',
    ];

    /**
     * @param  Configuration  $configuration
     * @param  array<string, string>  $details  The text per key of DETAILS, one item per line.
     */
    public function __construct(private readonly array $configuration, private readonly array $details = []) {}

    /**
     * Sections with their lines, in the order of the PDF; empty sections are left out.
     *
     * @return array<string, list<string>>
     */
    public function sections(): array
    {
        $configuration = $this->configuration;
        $openings = (new ComposedUnit($configuration))->openingCounts();
        $openingLabels = [
            UnitConfiguration::WINDOW => ':count× raam (180 x 120 cm)',
            UnitConfiguration::SINGLE_WINDOW => ':count× enkel raam (94,5 x 120 cm)',
            UnitConfiguration::DOOR => ':count× buitendeur (87,5 x 212,5 cm)',
            UnitConfiguration::HATCH => ':count× uitgifteluik (80 cm, wind- en waterdicht)',
        ];

        $sections = [
            'Unit' => $this->unit(),
            'Ramen en deuren' => array_values(array_filter(array_map(
                fn (string $type): ?string => $openings[$type] > 0 ? __($openingLabels[$type], ['count' => $openings[$type]]) : null,
                array_keys($openingLabels),
            ))),
            'Toegang' => ($configuration['steps'] ?? false) ? [__('Opstapje voor de deuren op de begane grond')] : [],
            'Klimaat, sanitair en keuken' => array_map(fn (string $item): string => $this->name($item, UnitConfiguration::INTERIOR), $configuration['interior'] ?? []),
            'Elektra en netwerk' => $this->amounts($configuration['connections'] ?? [], UnitConfiguration::CONNECTIONS),
            'Meubilair' => $this->amounts($configuration['furniture'] ?? [], UnitConfiguration::FURNITURE),
            'Type gebruik' => empty($configuration['usage']) ? [] : [implode(', ', array_map(fn (string $usage): string => __(UnitConfiguration::USAGES[$usage]), $configuration['usage']))],
        ];

        foreach (self::DETAILS as $key => $detail) {
            $sections[$detail['section']] = [...($sections[$detail['section']] ?? []), ...self::lines($this->details[$key] ?? null)];
        }

        $ordered = [];
        foreach (self::ORDER as $section) {
            if (! empty($sections[$section])) {
                $ordered[__($section)] = $sections[$section];
            }
        }

        return $ordered;
    }

    /**
     * The non-empty lines of a text, without a leading dash or bullet, so a pasted list works as well.
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        $lines = array_map(fn (string $line): string => trim(preg_replace('/^\s*[-•*]\s+/u', '', $line) ?? $line), preg_split('/\R/u', (string) $text) ?: []);

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /**
     * The unit with its exact size as the supplier gives it, in millimetres.
     *
     * @return list<string>
     */
    private function unit(): array
    {
        $configuration = $this->configuration;
        $outer = UnitConfiguration::SIZES[$configuration['size']]['outer'];
        $heights = UnitConfiguration::HEIGHTS[$configuration['size']] ?? null;
        $total = UnitConfiguration::outerSize($configuration);
        $mm = UnitConfiguration::millimetres(...);

        return array_values(array_filter([
            UnitConfiguration::label($configuration),
            $heights
                ? __('Buitenmaat per unit :length x :depth x :height mm (l x b x h)', ['length' => $mm($outer['length']), 'depth' => $mm($outer['depth']), 'height' => $mm($heights['outer'])])
                : __('Buitenmaat per unit :length x :depth mm (l x b)', ['length' => $mm($outer['length']), 'depth' => $mm($outer['depth'])]),
            $heights ? __('Binnenhoogte :height mm', ['height' => $mm($heights['inner'])]) : null,
            $configuration['modules'] > 1
                ? __('Samen ca. :length x :depth mm', ['length' => $mm($total['length']), 'depth' => $mm($total['depth'])])
                : null,
            $configuration['floors'] > 1 ? trans_choice('{1} 1 laag|[2,*] :count lagen', $configuration['floors']) : null,
            __('Wanden en frame :colour', ['colour' => mb_strtolower(__(UnitConfiguration::COLORS[$configuration['colors']['cladding']]))]),
            __('Kozijnen van ramen en deuren wit (RAL 9010)'),
            ($configuration['floors'] > 1 && $configuration['stairs']) ? __('Buitentrap met bordes naar de deuren op de verdieping') : null,
        ]));
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
