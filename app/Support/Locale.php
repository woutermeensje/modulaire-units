<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Carbon;

/**
 * The languages of the public website. Dutch is the source language and lives on the root ("/huren/"); every
 * translation lives under its own prefix ("/en/rent/"). Content in another language is read from the translations
 * column of a model, see App\Models\Concerns\HasTranslations.
 */
class Locale
{
    public const SOURCE = 'nl';

    /**
     * Translated languages => [hreflang, Open Graph locale, name in its own language].
     *
     * @var array<string, array{hreflang: string, og: string, name: string}>
     */
    public const TRANSLATIONS = [
        'en' => ['hreflang' => 'en', 'og' => 'en_GB', 'name' => 'English'],
    ];

    /**
     * Source language details, in the same shape as TRANSLATIONS.
     *
     * @var array{hreflang: string, og: string, name: string}
     */
    public const SOURCE_DETAILS = ['hreflang' => 'nl', 'og' => 'nl_NL', 'name' => 'Nederlands'];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SOURCE, ...array_keys(self::TRANSLATIONS)];
    }

    public static function current(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, self::all(), true) ? $locale : self::SOURCE;
    }

    /**
     * The translated language whose content is shown, or null on the Dutch site.
     */
    public static function translated(): ?string
    {
        $locale = self::current();

        return $locale === self::SOURCE ? null : $locale;
    }

    public static function isSource(?string $locale = null): bool
    {
        return ($locale ?? self::current()) === self::SOURCE;
    }

    public static function activate(string $locale): void
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /**
     * Run a callback as if the given language is active, e.g. to build the URL of a page in the other language.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function using(string $locale, Closure $callback): mixed
    {
        $previous = self::current();

        if ($previous === $locale) {
            return $callback();
        }

        self::activate($locale);

        try {
            return $callback();
        } finally {
            self::activate($previous);
        }
    }

    /**
     * The URL prefix of a language: "" for Dutch, "en" for English.
     */
    public static function prefix(?string $locale = null): string
    {
        $locale ??= self::current();

        return $locale === self::SOURCE ? '' : $locale;
    }

    /**
     * The route name in a language: "units.show" stays "units.show" in Dutch and becomes "en.units.show" in English.
     */
    public static function routeName(string $name, ?string $locale = null): string
    {
        $locale ??= self::current();

        return $locale === self::SOURCE ? $name : $locale.'.'.$name;
    }

    /**
     * @return array{hreflang: string, og: string, name: string}
     */
    public static function details(?string $locale = null): array
    {
        $locale ??= self::current();

        return self::TRANSLATIONS[$locale] ?? self::SOURCE_DETAILS;
    }
}
