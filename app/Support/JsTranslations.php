<?php

namespace App\Support;

/**
 * The Dutch UI strings used by the JavaScript of the public website. On a translated site the layout prints their
 * translations as window.siteTranslations, which t() in resources/js/site/i18n.js reads.
 */
class JsTranslations
{
    /**
     * Every Dutch string passed to t() in the JavaScript, exactly as written there.
     *
     * @var list<string>
     */
    public const JS_STRINGS = [
        'Gekopieerd!',
        'Kopiëren mislukt',
        'Kopieer',
        'Gekopieerd',
        'Kopieer met Ctrl/Cmd+C',
        'Kopieer link',
        'Toevoegen aan offerte',
        'Toegevoegd aan offerte',
        'Mislukt, probeer opnieuw',
        'Mijn offerte',
        'Geen resultaten',
        'Je kunt maximaal :count afbeeldingen uploaden.',
        'Beschrijf eerst de unit.',
        'Claude denkt na…',
        'Er ging iets mis. Probeer het opnieuw.',
        'Klaar. Controleer het voorbeeld en pas zo nodig vakken aan voordat je opslaat.',
        'Geen verbinding met de server. Probeer het opnieuw.',
        "3D-foto's maken…",
    ];

    /**
     * The strings mapped to their translation in the current language.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return collect(self::JS_STRINGS)
            ->mapWithKeys(fn (string $text): array => [$text => __($text)])
            ->all();
    }
}
