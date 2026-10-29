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
 * @phpstan-type Design array{configuration: Configuration, title: string, excerpt: string, meta_description: string}
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
        - front (voorzijde) is de lange zijde van de voorste unit, back (achterzijde) die van de achterste unit:
          3 vakken bij 3x3, 6 vakken bij 6x3.
        - left (linkerzijde) en right (rechterzijde) lopen langs alle units: 3 vakken per module.
        - Vakken lopen van links naar rechts, gezien van buitenaf terwijl je recht voor die gevel staat.
          De rechterzijde begint dus bij de voorste unit, de linkerzijde bij de achterste unit.
        - Geef per gevel precies zoveel vakken als die gevel heeft.

        Kleuren zijn hexcodes (#rrggbb): cladding is de gevelbekleding, frame zijn de kozijnen, hoekprofielen en het dak.
        Vertaal kleurnamen naar een passende, ingetogen hexcode (bijv. antraciet #3d4a52, wit #f2f2ee, lichtgrijs #c9cdcf, zwart #1f2326).
        Achtergrond: "industrial" (Nederlands industrieterrein met asfalt), "meadow" (grasveld), "site" (bouwterrein) of "plain" (neutraal).

        Volg de beschrijving zo letterlijk mogelijk. Wat niet genoemd wordt, kies je zelf op een logische manier:
        een unit heeft minstens één deur; ramen verdeel je symmetrisch; zonder kleurwens gebruik je antraciet met zwarte kozijnen; zonder achtergrondwens "industrial".
        Als er een huidige configuratie is meegegeven en de beschrijving klinkt als een wijziging ("maak", "voeg toe", "verplaats"), pas dan alleen aan wat gevraagd wordt.

        Schrijf daarnaast in het Nederlands, zakelijk en zonder overdrijving:
        - title: korte producttitel, bijv. "Kantoorunit 6x3 met twee ramen".
        - excerpt: 1 à 2 zinnen voor de unitkaart op de website.
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
            'excerpt' => trim((string) ($data['excerpt'] ?? '')),
            'meta_description' => mb_substr(trim((string) ($data['meta_description'] ?? '')), 0, 160),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $slots = ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::SLOTS)]];
        $color = ['type' => 'string', 'description' => 'Hexcode, bijv. #3d4a52'];

        return [
            'type' => 'object',
            'properties' => [
                'size' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::SIZES)],
                'modules' => ['type' => 'integer', 'enum' => range(1, UnitConfiguration::MAX_MODULES), 'description' => 'Aantal gekoppelde units'],
                'walls' => [
                    'type' => 'object',
                    'properties' => [
                        'front' => $slots + ['description' => '3 vakken bij 3x3, 6 vakken bij 6x3'],
                        'back' => $slots + ['description' => '3 vakken bij 3x3, 6 vakken bij 6x3'],
                        'left' => $slots + ['description' => '3 vakken per module'],
                        'right' => $slots + ['description' => '3 vakken per module'],
                    ],
                    'required' => ['front', 'back', 'left', 'right'],
                    'additionalProperties' => false,
                ],
                'colors' => [
                    'type' => 'object',
                    'properties' => ['cladding' => $color, 'frame' => $color],
                    'required' => ['cladding', 'frame'],
                    'additionalProperties' => false,
                ],
                'background' => ['type' => 'string', 'enum' => array_keys(UnitConfiguration::BACKGROUNDS)],
                'title' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
            ],
            'required' => ['size', 'modules', 'walls', 'colors', 'background', 'title', 'excerpt', 'meta_description'],
            'additionalProperties' => false,
        ];
    }
}
