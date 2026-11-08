<?php

namespace App\Console\Commands;

use App\Exceptions\TranslationFailed;
use App\Models\Author;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\Vacancy;
use App\Services\ContentTranslator;
use App\Support\Locale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Signature('site:translate
    {--locale=en : De taal waarnaar vertaald wordt}
    {--type=* : Alleen deze soorten: pages, posts, units, products, vacancies, terms, categories, authors, menu, team, settings}
    {--id=* : Alleen de records met deze id\'s (in combinatie met --type)}
    {--force : Ook records die al vertaald zijn opnieuw vertalen}
    {--dry-run : Alleen tonen wat er vertaald zou worden}')]
#[Description('Vertaal de inhoud van de website (pagina\'s, blogs, units, menu, SEO-teksten …) met Claude naar een andere taal')]
class TranslateSiteContent extends Command
{
    /**
     * Content type => [model, description for Claude].
     *
     * @var array<string, array{0: class-string<Model>, 1: string}>
     */
    private const TYPES = [
        'pages' => [Page::class, 'web page'],
        'posts' => [Post::class, 'blog article'],
        'units' => [Unit::class, 'modular unit product page'],
        'products' => [Product::class, 'shop product'],
        'vacancies' => [Vacancy::class, 'job vacancy'],
        'terms' => [UnitTerm::class, 'unit category'],
        'categories' => [Category::class, 'blog category'],
        'authors' => [Author::class, 'blog author'],
        'menu' => [MenuItem::class, 'navigation menu item'],
        'team' => [TeamMember::class, 'team member'],
    ];

    /**
     * Limits per request to Claude, so long pages are translated on their own and short texts are combined.
     */
    private const BATCH_CHARACTERS = 6000;

    private const BATCH_FIELDS = 40;

    /**
     * Attributes that are not sent to Claude: the page path is rebuilt from the slugs, menu URLs point to the
     * translated page automatically and author names stay as they are.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const COPIED = [
        Page::class => ['path'],
        MenuItem::class => ['url'],
        Author::class => ['name', 'slug'],
    ];

    /**
     * Texts in the hero of a landing page.
     *
     * @var list<string>
     */
    private const HERO_TEXTS = ['eyebrow', 'title', 'subtitle', 'button_text'];

    private int $translated = 0;

    private int $failed = 0;

    public function handle(ContentTranslator $translator): int
    {
        $locale = (string) $this->option('locale');

        if (! array_key_exists($locale, Locale::TRANSLATIONS)) {
            $this->components->error("Onbekende taal \"{$locale}\". Beschikbaar: ".implode(', ', array_keys(Locale::TRANSLATIONS)).'.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! ContentTranslator::isConfigured()) {
            $this->components->error('Vul ANTHROPIC_API_KEY in de .env in om te kunnen vertalen.');

            return self::FAILURE;
        }

        $types = $this->option('type') ?: [...array_keys(self::TYPES), 'settings'];

        foreach ($types as $type) {
            if ($type === 'settings') {
                $this->translateSettings($translator, $locale);

                continue;
            }

            if (! isset(self::TYPES[$type])) {
                $this->components->warn("Onbekende soort \"{$type}\" overgeslagen.");

                continue;
            }

            $this->translateType($translator, $type, $locale);
        }

        $this->newLine();
        $this->components->info("{$this->translated} vertaald, {$this->failed} mislukt.");

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function translateType(ContentTranslator $translator, string $type, string $locale): void
    {
        [$model, $description] = self::TYPES[$type];

        $records = $model::query()
            ->when($this->option('id'), fn ($query, array $ids) => $query->whereKey($ids))
            ->get()
            ->reject(fn (Model $record): bool => ! $this->option('force') && $record->hasTranslation($locale))
            ->when($model === Page::class, fn (Collection $pages) => $pages->sortBy(fn (Page $page): int => substr_count($page->path, '/') + ($page->path === '' ? 0 : 1)))
            ->values();

        $this->components->task("{$type}: {$records->count()} te vertalen", fn (): bool => true);

        if ($records->isEmpty() || $this->option('dry-run')) {
            $records->each(fn (Model $record) => $this->line('  - '.$this->label($record)));

            return;
        }

        foreach ($this->batches($records, $description) as $batch) {
            $fields = array_merge(...array_column($batch, 'fields'));

            try {
                $translations = $translator->translate($fields, $locale, $this->promptContext($batch, $description));
            } catch (TranslationFailed $exception) {
                $this->failed += count($batch);
                $this->components->error(implode(', ', array_map(fn (array $item): string => $this->label($item['record']), $batch)).': '.$exception->getMessage());

                continue;
            }

            foreach ($batch as $item) {
                $this->store($item['record'], $translations, $locale);
                $this->translated++;
                $this->components->twoColumnDetail($this->label($item['record']), 'vertaald');
            }
        }
    }

    /**
     * Group records into requests: one long page per request, many short texts (menu items, terms) together.
     *
     * @param  Collection<int, Model>  $records
     * @return list<list<array{record: Model, fields: array<string, array{text: string, instruction?: string}>}>>
     */
    private function batches(Collection $records, string $description): array
    {
        $batches = [];
        $current = [];
        $characters = 0;
        $fieldCount = 0;

        foreach ($records as $record) {
            $fields = $this->fields($record, $description);
            $size = array_sum(array_map(fn (array $field): int => mb_strlen($field['text']), $fields));

            if ($current !== [] && ($characters + $size > self::BATCH_CHARACTERS || $fieldCount + count($fields) > self::BATCH_FIELDS)) {
                $batches[] = $current;
                $current = [];
                $characters = 0;
                $fieldCount = 0;
            }

            $current[] = ['record' => $record, 'fields' => $fields];
            $characters += $size;
            $fieldCount += count($fields);
        }

        if ($current !== []) {
            $batches[] = $current;
        }

        return $batches;
    }

    /**
     * The Dutch texts of a record, keyed "{id}__{attribute}", with instructions for slugs and SEO texts.
     *
     * @return array<string, array{text: string, instruction?: string}>
     */
    private function fields(Model $record, string $description): array
    {
        $fields = [];
        $prefix = $record->getKey().'__';
        $attributes = $record->getAttributes();

        foreach (array_diff($record::translatableAttributes(), self::COPIED[$record::class] ?? []) as $attribute) {
            if ($attribute === 'hero') {
                foreach (self::HERO_TEXTS as $heroText) {
                    $value = (string) ($record->hero[$heroText] ?? '');

                    if ($value !== '') {
                        $fields[$prefix.'hero_'.$heroText] = ['text' => $value];
                    }
                }

                continue;
            }

            $value = (string) ($attributes[$attribute] ?? '');

            $field = match ($attribute) {
                'slug' => $value === '' ? null : ['text' => $value, 'instruction' => "English URL slug for this {$description}: lowercase words joined by hyphens, 1 to 5 descriptive keywords, no stop words."],
                'meta_title' => ['text' => $value, 'instruction' => 'SEO title tag of at most 60 characters with the main keyword first. If the Dutch text is empty, write a new one from the content.'],
                'meta_description' => ['text' => $value, 'instruction' => 'SEO meta description of 120 to 155 characters that makes searchers click, with the main keyword. If the Dutch text is empty, write a new one from the content.'],
                default => $value === '' ? null : ['text' => $value],
            };

            if ($field !== null) {
                $fields[$prefix.$attribute] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  list<array{record: Model, fields: array<string, mixed>}>  $batch
     */
    private function promptContext(array $batch, string $description): string
    {
        $lines = array_map(fn (array $item): string => '- fields '.$item['record']->getKey().'__*: '.$this->label($item['record']), $batch);

        return "The fields belong to this {$description} content (field names are \"{id}__{attribute}\"):\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function store(Model $record, array $translations, string $locale): void
    {
        $prefix = $record->getKey().'__';
        $values = [];

        foreach ($translations as $key => $value) {
            if (str_starts_with($key, $prefix) && ! str_starts_with(substr($key, strlen($prefix)), 'hero_')) {
                $values[substr($key, strlen($prefix))] = $value;
            }
        }

        if (in_array('hero', $record::translatableAttributes(), true) && is_array($record->hero)) {
            $hero = $record->hero;

            foreach (self::HERO_TEXTS as $heroText) {
                if (isset($translations[$prefix.'hero_'.$heroText])) {
                    $hero[$heroText] = $translations[$prefix.'hero_'.$heroText];
                }
            }

            $values['hero'] = $hero;
        }

        foreach (self::COPIED[$record::class] ?? [] as $attribute) {
            if ($attribute !== 'path' && $attribute !== 'url') {
                $values[$attribute] = $record->getAttributes()[$attribute] ?? null;
            }
        }

        if (in_array('slug', $record::translatableAttributes(), true) && ! in_array('slug', self::COPIED[$record::class] ?? [], true)) {
            $values['slug'] = ($record->getAttributes()['slug'] ?? '') === '' ? '' : $this->uniqueSlug($record, Str::slug($values['slug'] ?? '') ?: (string) $record->getAttributes()['slug'], $locale);
        }

        $record->setTranslations($locale, $values)->save();
    }

    /**
     * A translated slug that no other record (for pages: no sibling page) uses yet in this language.
     */
    private function uniqueSlug(Model $record, string $slug, string $locale): string
    {
        $candidate = $slug;
        $counter = 2;

        while ($record::query()
            ->whereKeyNot($record->getKey())
            ->when($record instanceof Page, fn ($query) => $query->where('parent_id', $record->parent_id))
            ->when($record instanceof UnitTerm, fn ($query) => $query->where('taxonomy', $record->taxonomy))
            ->where('translations->'.$locale.'->slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$counter++;
        }

        return $candidate;
    }

    private function translateSettings(ContentTranslator $translator, string $locale): void
    {
        $stored = Setting::query()->pluck('value', 'key');
        $fields = [];

        foreach (Setting::TRANSLATABLE as $key) {
            $source = (string) Setting::get($key);

            if ($source !== '' && ($this->option('force') || blank($stored[$key.'_'.$locale] ?? null))) {
                $fields[$key] = ['text' => $source];
            }
        }

        $this->components->task('settings: '.count($fields).' te vertalen', fn (): bool => true);

        if ($fields === [] || $this->option('dry-run')) {
            return;
        }

        try {
            $translations = $translator->translate($fields, $locale, 'These are short texts used across the website (slogan, footer text, discount pop-up).');
        } catch (TranslationFailed $exception) {
            $this->failed++;
            $this->components->error('settings: '.$exception->getMessage());

            return;
        }

        foreach ($translations as $key => $value) {
            Setting::put($key.'_'.$locale, $value);
            $this->translated++;
        }
    }

    private function label(Model $record): string
    {
        $attributes = $record->getAttributes();
        $name = $attributes['title'] ?? $attributes['name'] ?? $attributes['label'] ?? $attributes['role'] ?? '';

        return trim(class_basename($record).' #'.$record->getKey().' '.$name.(isset($attributes['path']) ? ' (/'.$attributes['path'].')' : ''));
    }
}
