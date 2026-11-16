<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use App\Exceptions\UnitDesignFailed;
use App\Support\UnitConfiguration;
use JsonException;

/**
 * Turns a free-text description of a modular unit into a configuration plus website texts, using Claude.
 *
 * @phpstan-import-type Configuration from UnitConfiguration
 *
 * @phpstan-type Design array{configuration: Configuration, title: string, meta_description: string}
 */
class UnitDesigner
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Je helpt een verkoper van modulaire units (kantoor-, sanitaire en opslagunits) om een unit te configureren en te beschrijven voor de website.

        Er zijn twee afmetingen: 3x3 (3 bij 3 meter) en 6x3 (6 meter lang, 3 meter diep).
        Met modules koppel je 1 tot 4 units van dezelfde afmeting met de lange zijde tegen elkaar tot één open ruimte,
        bijv. 3 modules van 6x3 geeft een ruimte van 6 bij 9 meter. Gebruik 1 module tenzij de beschrijving om meer ruimte
        of gekoppelde units vraagt.
        Elke gevel is verdeeld in vakken van 1 meter. Een vak is "empty" (dichte wand), "window" (raam) of "door" (deur).
        Een raam is 180 cm breed en beslaat twee aangrenzende vakken binnen dezelfde unit: geef beide vakken "window".
        Drie aangrenzende "window"-vakken zijn één raam precies in het midden ervan; zo zet je een raam midden in een
        kopgevel van 3 meter: ["window", "window", "window"]. Laat tussen losse ramen minstens één vak "empty".
        Een deur (87,5 cm breed) beslaat één vak.
        Een vak kan ook "single_window" zijn: een enkel, standaard kantoorraam van 94,5 cm breed dat één vak beslaat. Gebruik
        het als er om een klein of smal raam gevraagd wordt, of waar voor een raam van 180 cm geen twee vakken vrij zijn.
        Een vak kan ook "hatch" zijn: een wind- en waterdicht uitgifteluik (80 cm breed), bijv. voor een portiersloge of
        het uitgeven van passen; een luik beslaat één vak en is geen raam.
        - front (voorzijde) is de lange zijde van de voorste unit, back (achterzijde) die van de achterste unit:
          3 vakken bij 3x3, 6 vakken bij 6x3.
        - left (linkerzijde) en right (rechterzijde) lopen langs alle units: 3 vakken per module.
        - Vakken lopen van links naar rechts, gezien van buitenaf terwijl je recht voor die gevel staat.
          De rechterzijde begint dus bij de voorste unit, de linkerzijde bij de achterste unit.
        - Geef per gevel precies zoveel vakken als die gevel heeft.
        Met floors kies je 1 of 2 lagen: bij 2 staat er op elke unit een tweede unit. walls zijn de gevels van de begane grond,
        upper_walls die van de verdieping, met hetzelfde aantal vakken. Gebruik 1 laag tenzij om stapelen of een verdieping
        gevraagd wordt; geef upper_walls dan dezelfde vakken als walls, ze worden genegeerd.
        stairs zet bij elke deur op de verdieping een buitentrap met bordes; zet het aan bij 2 lagen, tenzij er geen trap gewenst is.
        Een verdieping heeft voor de trap minstens één deur nodig.

        Kleuren zijn hexcodes: cladding is de kleur van de wanden; het stalen frame krijgt automatisch dezelfde kleur en de
        kozijnen zijn altijd wit. Er zijn vier kleuren: "#f2f0e8" (wit), "#383e42" (antraciet), "#0e4c92" (blauw) en "#777c7e" (grijs).
        Kies bij andere kleurwensen de kleur die er het dichtst bij ligt.
        interior is de inrichting, een lijst met: "airco" (airco aan de wand in elke unit, koelt en verwarmt), "heater"
        (elektrische verwarming laag aan de wand in elke unit; te combineren met airco), "toilet" (toiletruimte met fonteintje)
        en "kitchen" (keukentje). Verlichting zit er altijd in. Kies alleen wat gevraagd wordt
        of duidelijk bij het gebruik hoort; anders een lege lijst. Waar het komt te staan wordt automatisch bepaald.
        steps zet een opstapje voor de deuren op de begane grond. tentative is een lijst met keuzes uit interior
        die de klant nog niet zeker weet (bijv. "toilet als optie"); anders een lege lijst.
        Achtergrond: "industrial" (Nederlands industrieterrein met asfalt), "meadow" (grasveld), "site" (bouwterrein) of "plain" (neutraal).

        Volg de beschrijving zo letterlijk mogelijk. Wat niet genoemd wordt, kies je zelf op een logische manier:
        een unit heeft minstens één deur; ramen verdeel je symmetrisch; zonder kleurwens gebruik je witte wanden; zonder achtergrondwens "industrial".
        Als er een huidige configuratie is meegegeven en de beschrijving klinkt als een wijziging ("maak", "voeg toe", "verplaats"), pas dan alleen aan wat gevraagd wordt.

        Schrijf daarnaast in het Nederlands, zakelijk en zonder overdrijving:
        - title: korte producttitel, bijv. "Kantoorunit 6x3 met twee ramen".
        - meta_description: maximaal 155 tekens, voor zoekmachines.
        PROMPT;

    public function __construct(private Client $client) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  array<string, mixed>|null  $current  The configuration currently in the form, so the description can refer to it.
     * @return Design
     *
     * @throws UnitDesignFailed
     */
    public function design(string $description, ?array $current = null): array
    {
        $prompt = 'Beschrijving: '.$description;
        if ($current !== null) {
            $prompt .= "\n\nHuidige configuratie:\n".json_encode(UnitConfiguration::normalize($current), JSON_PRETTY_PRINT);
        }

        $message = $this->client->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 16000,
            system: self::SYSTEM_PROMPT,
            messages: [['role' => 'user', 'content' => $prompt]],
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            throw new UnitDesignFailed('Claude kon deze beschrijving niet verwerken. Probeer het anders te formuleren.');
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                return $this->parse($block->text);
            }
        }

        throw new UnitDesignFailed('Claude gaf geen bruikbaar antwoord. Probeer het opnieuw.');
    }

    /**
     * @return Design
     *
     * @throws UnitDesignFailed
     */
    public function parse(string $json): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnitDesignFailed('Claude gaf geen bruikbaar antwoord. Probeer het opnieuw.');
        }

        if (! is_array($data)) {
            throw new UnitDesignFailed('Claude gaf geen bruikbaar antwoord. Probeer het opnieuw.');
        }

        return [
            'configuration' => UnitConfiguration::normalize($data),
            'title' => trim((string) ($data['title'] ?? '')),
            'meta_description' => mb_substr(trim((string) ($data['meta_description'] ?? '')), 0, 160),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $slots = ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::SLOTS)]];
        $walls = [
            'type' => 'object',
            'properties' => [
                'front' => $slots + ['description' => '3 vakken bij 3x3, 6 vakken bij 6x3'],
                'back' => $slots + ['description' => '3 vakken bij 3x3, 6 vakken bij 6x3'],
                'left' => $slots + ['description' => '3 vakken per module'],
                'right' => $slots + ['description' => '3 vakken per module'],
            ],
            'required' => ['front', 'back', 'left', 'right'],
            'additionalProperties' => false,
        ];
        $color = ['type' => 'string', 'enum' => array_keys(UnitConfiguration::COLORS)];

        return [
            'type' => 'object',
            'properties' => [
                'size' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::SIZES)],
                'modules' => ['type' => 'integer', 'enum' => range(1, UnitConfiguration::MAX_MODULES), 'description' => 'Aantal gekoppelde units'],
                'floors' => ['type' => 'integer', 'enum' => range(1, UnitConfiguration::MAX_FLOORS), 'description' => 'Aantal lagen'],
                'walls' => $walls,
                'upper_walls' => $walls,
                'stairs' => ['type' => 'boolean', 'description' => 'Buitentrap naar de deuren op de verdieping'],
                'colors' => [
                    'type' => 'object',
                    'properties' => ['cladding' => $color],
                    'required' => ['cladding'],
                    'additionalProperties' => false,
                ],
                'background' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::BACKGROUNDS)],
                'interior' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::INTERIOR)], 'description' => 'Inrichting; verlichting zit er altijd in'],
                'steps' => ['type' => 'boolean', 'description' => 'Opstapje voor de deuren op de begane grond'],
                'tentative' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::INTERIOR)], 'description' => 'Keuzes die nog niet zeker zijn'],
                'title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
            ],
            'required' => ['size', 'modules', 'floors', 'walls', 'upper_walls', 'stairs', 'colors', 'background', 'interior', 'steps', 'tentative', 'title', 'meta_description'],
            'additionalProperties' => false,
        ];
    }

    /**
     * An object with a whole number for every option.
     *
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private function amounts(array $options, string $description): array
    {
        return [
            'type' => 'object',
            'properties' => array_map(fn (string $label): array => ['type' => 'integer', 'description' => $label], $options),
            'required' => array_keys($options),
            'additionalProperties' => false,
            'description' => $description,
        ];
    }
}
