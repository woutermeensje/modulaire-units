<?php

namespace App\Models\Concerns;

use App\Support\Locale;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Content in other languages, stored in the JSON column "translations" as {"en": {"title": "…", "slug": "…"}}.
 * While a translated language is active (on /en/), the translatable attributes return the translation, so views
 * and URLs don't need to know about languages. A missing translation falls back to the Dutch value.
 *
 * A model using this trait lists its translatable attributes in the TRANSLATABLE constant.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
    }

    /**
     * @return list<string>
     */
    public static function translatableAttributes(): array
    {
        return static::TRANSLATABLE;
    }

    public function getAttributeValue($key): mixed
    {
        if ($key !== 'translations' && ($locale = Locale::translated()) !== null && in_array($key, static::TRANSLATABLE, true)) {
            $translation = $this->translation($locale, $key);

            if ($translation !== null && $translation !== '' && $translation !== []) {
                return $translation;
            }
        }

        return parent::getAttributeValue($key);
    }

    /**
     * The untranslated (Dutch) value of an attribute, also while another language is active.
     */
    public function source(string $key): mixed
    {
        return Locale::using(Locale::SOURCE, fn (): mixed => $this->getAttribute($key));
    }

    public function translation(string $locale, string $key): mixed
    {
        return $this->allTranslations()[$locale][$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allTranslations(): array
    {
        return (array) (parent::getAttributeValue('translations') ?? []);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setTranslations(string $locale, array $values): static
    {
        $translations = $this->allTranslations();
        $translations[$locale] = array_merge($translations[$locale] ?? [], $values);
        $this->translations = $translations;

        return $this;
    }

    /**
     * A record counts as translated once its title (or name, label) is translated; only then does it get a URL in that language.
     */
    public function hasTranslation(?string $locale = null): bool
    {
        $locale ??= Locale::current();

        if (Locale::isSource($locale)) {
            return true;
        }

        return filled($this->translation($locale, static::TRANSLATABLE[0]));
    }

    /**
     * The language a link to this record should be in: the active language when it is translated, otherwise Dutch.
     */
    public function urlLocale(): string
    {
        return $this->hasTranslation() ? Locale::current() : Locale::SOURCE;
    }

    /**
     * Find records by an attribute in the active language, e.g. the slug from the URL.
     */
    #[Scope]
    protected function whereLocalized(Builder $query, string $column, mixed $value): void
    {
        $locale = Locale::translated();

        $locale === null
            ? $query->where($this->qualifyColumn($column), $value)
            : $query->where($this->qualifyColumn('translations').'->'.$locale.'->'.$column, $value);
    }

    /**
     * Only records that have a translation in the active language (all records on the Dutch site).
     */
    #[Scope]
    protected function translated(Builder $query): void
    {
        $locale = Locale::translated();

        if ($locale !== null) {
            $query->whereNotNull($this->qualifyColumn('translations').'->'.$locale.'->'.static::TRANSLATABLE[0]);
        }
    }

    /**
     * Search attributes with LIKE in the active language.
     *
     * @param  list<string>  $columns
     */
    #[Scope]
    protected function whereLocalizedLike(Builder $query, array $columns, string $like): void
    {
        $locale = Locale::translated();

        $query->where(function (Builder $query) use ($columns, $like, $locale): void {
            foreach ($columns as $column) {
                $query->orWhere($locale === null ? $this->qualifyColumn($column) : $this->qualifyColumn('translations').'->'.$locale.'->'.$column, 'like', $like);
            }
        });
    }
}
