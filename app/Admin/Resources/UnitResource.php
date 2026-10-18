<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Media;
use App\Models\Unit;
use App\Support\SiteUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class UnitResource extends Resource
{
    public string $model = Unit::class;

    public string $key = 'units';

    public string $singular = 'Unit';

    public string $plural = 'Units';

    public string $icon = 'ph-cube';

    public ?string $description = 'Het aanbod modulaire units. Units verschijnen automatisch in de catalogi op de koop-, huur- en gebruiktpagina\'s.';

    public function query(Builder $query): Builder
    {
        return $query->with('terms')->orderBy('sort')->latest('published_at');
    }

    public function columns(): array
    {
        return [
            'Foto' => fn (Unit $unit) => $unit->featured_image
                ? new HtmlString('<img class="portal-thumb" src="'.e(SiteUrl::media(Media::variant($unit->featured_image, 'thumbnail'))).'" alt="">')
                : '',
            'Titel' => fn (Unit $unit) => new HtmlString('<strong>'.e($unit->title).'</strong>'),
            'Kenmerken' => fn (Unit $unit) => $unit->terms->pluck('name')->implode(', '),
            'Status' => fn (Unit $unit) => $unit->status,
            'Volgorde' => fn (Unit $unit) => $unit->sort,
        ];
    }

    public function fields(?Model $record): array
    {
        return [
            'Unit' => [
                Field::make('title', 'Titel')->required(),
                Field::make('slug', 'Slug', 'slug')->slugFrom('title'),
                Field::make('excerpt', 'Korte omschrijving', 'textarea')->rows(3)->help('Getoond op de unitkaart en bovenaan de unitpagina.'),
                Field::make('content', 'Unit beschrijving', 'richtext')->rows(16),
                Field::make('terms', 'Kenmerken', 'terms'),
            ],
            'Prijzen' => [
                Field::make('price_rent', 'Prijsindicatie huren')->half()->placeholder('Bijv. Vanaf € 250,- per maand'),
                Field::make('price_buy', 'Prijsindicatie kopen')->half()->placeholder('Bijv. Prijs op aanvraag.'),
            ],
            "Foto's" => [
                Field::make('featured_image', 'Hoofdfoto', 'image')->half(),
                Field::make('featured_image_alt', 'Alt-tekst hoofdfoto')->half(),
                Field::make('gallery', "Extra foto's", 'gallery'),
            ],
            'Publicatie' => [
                Field::make('status', 'Status', 'select')->options($this->statusOptions())->half(),
                Field::make('sort', 'Volgorde', 'number')->half(),
                Field::make('published_at', 'Publicatiedatum', 'datetime')->half(),
            ],
            'SEO' => $this->seoFields(),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('units', 'slug')->ignore($record?->id)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'price_rent' => ['nullable', 'string', 'max:190'],
            'price_buy' => ['nullable', 'string', 'max:190'],
            'featured_image_upload' => ['nullable', 'image', 'max:10240'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'gallery_upload.*' => ['image', 'max:10240'],
            'terms' => ['array'],
            'terms.*' => ['integer', Rule::exists('unit_terms', 'id')],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'sort' => ['nullable', 'integer'],
            'published_at' => ['nullable', 'date'],
        ] + $this->seoRules();
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['sort'] = (int) ($attributes['sort'] ?? 0);
        $attributes['published_at'] = ($attributes['published_at'] ?? null) ?: (($attributes['status'] ?? null) === 'published' ? now() : null);

        return $attributes;
    }

    public function afterSave(Model $record, Request $request): void
    {
        /** @var Unit $record */
        $record->terms()->sync(array_map('intval', (array) $request->input('terms', [])));
    }
}
