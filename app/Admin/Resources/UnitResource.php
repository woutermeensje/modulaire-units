<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Media;
use App\Models\Unit;
use App\Services\UnitConfigurationImages;
use App\Support\SiteUrl;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
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
            'Configuratie' => [
                Field::make('configuration', 'Unit-configurator', 'configurator')
                    ->help('Kies een afmeting en klik per gevel op een vak om te wisselen tussen dicht, raam en deur. Bij opslaan komen de afbeeldingen op de website: als hoofdfoto als de unit nog geen foto heeft, anders achteraan in de galerij. Kies of dat de 2D-tekeningen of de 3D-beelden zijn.'),
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
            'photos_3d_enabled' => ['nullable', 'boolean'],
            'photos_3d' => ['nullable', 'array:'.implode(',', array_keys(UnitRenderer::VIEWS))],
            'photos_3d.*' => ['image', 'mimes:jpg,jpeg', 'max:10240'],
        ] + UnitConfiguration::rules('configuration') + $this->seoRules();
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['sort'] = (int) ($attributes['sort'] ?? 0);
        $attributes['configuration'] = filled($validated['configuration']['size'] ?? null)
            ? UnitConfiguration::normalize($validated['configuration'])
            : null;
        $attributes['published_at'] = ($attributes['published_at'] ?? null) ?: (($attributes['status'] ?? null) === 'published' ? now() : null);

        return $attributes;
    }

    public function afterSave(Model $record, Request $request): void
    {
        /** @var Unit $record */
        $record->terms()->sync(array_map('intval', (array) $request->input('terms', [])));
        $usesPhotos = $request->boolean('photos_3d_enabled');
        app(UnitConfigurationImages::class)->sync($record, $usesPhotos ? $request->file('photos_3d', []) : [], keepPhotos: $usesPhotos);
    }
}
