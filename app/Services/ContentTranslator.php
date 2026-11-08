<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use App\Exceptions\TranslationFailed;
use App\Support\Locale;
use JsonException;

/**
 * Translates website content from Dutch with Claude. Texts are sent as a set of named fields and come back under the
 * same names; HTML must keep exactly the same markup, which is checked (with one retry) before a result is accepted.
 *
 * @phpstan-type TranslationField array{text: string, instruction?: string}
 */
class ContentTranslator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You translate the Dutch website of ModulaireHuisvesting.nl into %s. The company sells and rents modular units,
        portable cabins and containers (office units, sanitary units, canteens, classrooms, changing rooms, temporary
        buildings) in the Netherlands, and offers transport, installation, interior fitting and maintenance.

        Write natural, professional %s for business customers, addressing the reader as "you". Translate the meaning,
        not word by word, and write as a native copywriter would. Optimise for search: use the terms English-speaking
        customers search for, e.g. "modular units", "portable cabins", "site cabins", "office containers",
        "temporary accommodation", "for hire" / "to rent" and "for sale" / "to buy". Use this glossary consistently:
        - modulaire unit(s): modular unit(s); unit samensteller / unit samenstellen: unit configurator / configure a unit
        - huren / huur: rent / hire; kopen / koop: buy / purchase; gebruikt: used; offerte: quote
        - bouwunit: site cabin; kantoorunit: office unit; sanitaire unit: sanitary unit; kantine: canteen
        - schakelunit: linkable modular unit; flex-unit: flex unit; slaapunit: sleeping unit; woonunit: residential unit
        - klaslokaal: classroom; modulaire kleedkamer: modular changing room; noodgebouw: temporary building
        - portiersloge: gatehouse; festivalunit: festival unit; tijdelijke opslag: temporary storage
        - transport & plaatsing: transport & installation; inrichting: interior fitting; onderhoud: maintenance
        Keep names (ModulaireHuisvesting.nl, people, places), e-mail addresses, phone numbers, prices and dimensions as they are.

        Rules for HTML: keep every tag, attribute, class, id, style and URL exactly as it is, in the same order.
        Only translate the visible text and the alt, title, placeholder and aria-label attributes.
        Shortcodes in square brackets such as [units_grid type="kantoorunit"] stay exactly as they are.

        Each field may have its own instruction; follow it. Return every field, under the same name.
        PROMPT;

    public function __construct(private Client $client) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  array<string, TranslationField>  $fields
     * @return array<string, string>
     *
     * @throws TranslationFailed
     */
    public function translate(array $fields, string $locale, string $context = ''): array
    {
        $fields = array_filter($fields, fn (array $field): bool => trim($field['text']) !== '' || isset($field['instruction']));

        if ($fields === []) {
            return [];
        }

        $translations = $this->request($fields, $locale, $context);
        $broken = $this->brokenMarkup($fields, $translations);

        if ($broken !== []) {
            $translations = array_merge($translations, $this->request(
                array_intersect_key($fields, array_flip($broken)),
                $locale,
                $context."\n\nA previous attempt changed the HTML markup of these fields. Keep every tag exactly as in the source.",
            ));

            if (($broken = $this->brokenMarkup($fields, $translations)) !== []) {
                throw new TranslationFailed('The HTML of '.implode(', ', $broken).' changed during translation.');
            }
        }

        return $translations;
    }

    /**
     * The markup of HTML: the sequence of tags with their href/src, plus the shortcodes. Text may change, this may not.
     */
    public static function skeleton(string $html): string
    {
        preg_match_all('~<(/?[a-z][a-z0-9-]*)\b([^>]*)>|\[([a-z_]+)\b[^\]]*\]~i', $html, $matches, PREG_SET_ORDER);

        return implode('|', array_map(function (array $match): string {
            if (($match[3] ?? '') !== '') {
                return '['.strtolower($match[3]);
            }

            preg_match('~\s(?:href|src)\s*=\s*["\']([^"\']*)["\']~i', $match[2], $url);

            return strtolower($match[1]).($url[1] ?? '');
        }, $matches));
    }

    /**
     * @param  array<string, TranslationField>  $fields
     * @param  array<string, string>  $translations
     * @return list<string>
     */
    private function brokenMarkup(array $fields, array $translations): array
    {
        $broken = [];

        foreach ($fields as $key => $field) {
            $translation = $translations[$key] ?? null;

            if ($translation === null || (str_contains($field['text'], '<') && self::skeleton($field['text']) !== self::skeleton($translation))) {
                $broken[] = $key;
            }
        }

        return $broken;
    }

    /**
     * @param  array<string, TranslationField>  $fields
     * @return array<string, string>
     *
     * @throws TranslationFailed
     */
    private function request(array $fields, string $locale, string $context): array
    {
        $language = Locale::details($locale)['name'];
        $properties = [];

        foreach ($fields as $key => $field) {
            $properties[$key] = ['type' => 'string'] + (isset($field['instruction']) ? ['description' => $field['instruction']] : []);
        }

        $source = array_map(fn (array $field): string => $field['text'], $fields);
        $prompt = trim($context."\n\nTranslate these fields from Dutch:\n".json_encode($source, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $message = $this->client->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 32000,
            system: sprintf(self::SYSTEM_PROMPT, $language, $language),
            messages: [['role' => 'user', 'content' => $prompt]],
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => array_keys($properties),
                'additionalProperties' => false,
            ]]],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            throw new TranslationFailed('Claude refused to translate this content.');
        }

        if ($message->stopReason === 'max_tokens') {
            throw new TranslationFailed('The translation was cut off because it is too long.');
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                try {
                    $data = json_decode($block->text, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new TranslationFailed('Claude returned invalid JSON.');
                }

                return array_map(fn (mixed $value): string => trim((string) $value), array_intersect_key((array) $data, $fields));
            }
        }

        throw new TranslationFailed('Claude returned no translation.');
    }
}
