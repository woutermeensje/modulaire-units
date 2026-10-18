<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageResource extends Resource
{
    public string $model = Page::class;

    public string $key = 'pages';

    public string $singular = 'Pagina';

    public string $plural = "Pagina's";

    public string $icon = 'ph-files';

    public ?string $description = "Alle pagina's van de website. De URL is opgebouwd uit de bovenliggende pagina + slug.";

    public array $searchable = ['title', 'path'];

    public int $perPage = 100;

    public function query(Builder $query): Builder
    {
        return $query->orderBy('path');
    }

    public function columns(): array
    {
        return [
            'Titel' => fn (Page $page) => new HtmlString('<strong>'.e($page->title).'</strong>'),
            'URL' => fn (Page $page) => new HtmlString('<code>'.e($page->url()).'</code>'),
            'Template' => fn (Page $page) => Page::TEMPLATES[$page->template] ?? $page->template,
            'Status' => fn (Page $page) => $page->status,
            'Laatste update' => fn (Page $page) => $page->updated_at?->format('d-m-Y H:i'),
        ];
    }

    public function fields(?Model $record): array
    {
        $parents = Page::query()->when($record?->exists, fn ($q) => $q->whereKeyNot($record->id))
            ->orderBy('path')->get()
            ->mapWithKeys(fn (Page $page): array => [$page->id => ($page->path === '' ? 'Home' : $page->url()).' — '.$page->title])
            ->prepend('— Geen (hoofdniveau) —', '')
            ->all();

        return [
            'Pagina' => [
                Field::make('title', 'Titel')->required()->half(),
                Field::make('slug', 'Slug', 'slug')->half()->slugFrom('title')->help($record?->path === '' ? 'Dit is de homepage; de slug blijft leeg.' : 'Laatste deel van de URL.'),
                Field::make('parent_id', 'Bovenliggende pagina', 'select')->options($parents)->half(),
                Field::make('template', 'Template', 'select')->options(Page::TEMPLATES)->half(),
                Field::make('show_title', 'Toon de paginatitel boven de inhoud', 'checkbox'),
                Field::make('hero', 'Hero (alleen bij template "Landingspagina")', 'hero'),
                Field::make('content', 'Inhoud (HTML)', 'code')->rows(28)->help('HTML van de pagina. Dynamische blokken: [units_catalog title="…" type="slug"], [units_grid type="slug" limit="3"], [subpage_grid parent="kopen" columns="3"], [team columns="3"], [vacatures], [informatie_formulier].'),
                Field::make('custom_css', 'Pagina-specifieke CSS', 'code')->rows(8)->help('Overgenomen Elementor-opmaak van deze pagina.'),
                Field::make('excerpt', 'Samenvatting', 'textarea')->rows(3),
                Field::make('featured_image', 'Uitgelichte afbeelding', 'image')->half()->help('Wordt o.a. gebruikt als tegel in [subpage_grid].'),
            ],
            'Publicatie' => [
                Field::make('status', 'Status', 'select')->options($this->statusOptions())->half(),
                Field::make('menu_order', 'Volgorde', 'number')->half(),
            ],
            'SEO' => $this->seoFields(),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190'],
            'parent_id' => ['nullable', Rule::exists('pages', 'id')],
            'template' => ['required', Rule::in(array_keys(Page::TEMPLATES))],
            'content' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'featured_image_upload' => ['nullable', 'image', 'max:10240'],
            'hero_image_upload' => ['nullable', 'image', 'max:10240'],
            'hero' => ['nullable', 'array'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'menu_order' => ['nullable', 'integer'],
        ] + $this->seoRules();
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['parent_id'] = ($attributes['parent_id'] ?? null) ?: null;
        $attributes['menu_order'] = (int) ($attributes['menu_order'] ?? 0);

        if ($record->exists && $record->path === '') {
            $attributes['slug'] = '';
            $attributes['parent_id'] = null;
        }

        $path = trim((($attributes['parent_id'] ? Page::find($attributes['parent_id'])?->path.'/' : '').($attributes['slug'] ?? '')), '/');
        $duplicate = Page::query()->where('path', $path)->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['slug' => "Er bestaat al een pagina met de URL /{$path}/."]);
        }

        return $attributes;
    }
}
