/**
 * Translates a Dutch UI string for the language of the page. The translations are printed by layouts/site.blade.php
 * on a translated site only (see App\Support\JsTranslations), so on the Dutch site and in the portal the Dutch text
 * is returned as is. Placeholders such as ":count" are replaced from the given replacements, as Laravel's __() does.
 *
 * @param {string} text The Dutch text, as listed in App\Support\JsTranslations::JS_STRINGS.
 * @param {Object<string, string|number>} [replacements]
 * @returns {string}
 */
export function t(text, replacements = {}) {
    const translated = window.siteTranslations?.[text] ?? text;

    return Object.entries(replacements).reduce(
        (result, [key, value]) => result.replaceAll(`:${key}`, String(value)),
        translated,
    );
}
