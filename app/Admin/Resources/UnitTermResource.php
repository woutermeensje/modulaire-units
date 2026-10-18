<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\UnitTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UnitTermResource extends Resource
{
    public string $model = UnitTerm::class;

    public string $key = 'unit-terms';

    public string $singular = 'Unit-kenmerk';

    public string $plural = 'Unit-kenmerken';

    public string $icon = 'ph-tag';

    public ?string $description = 'Types units, condities (nieuw/gebruikt/…) en aanbod (koop/huur). Gebruikt voor filters en archiefpagina\'s.';

    public array $searchable = ['name', 'slug'];

    public int $perPage = 100;

    public function query(Builder $query): Builder
    {
        return $query->withCount('units')->orderBy('taxonomy')->orderBy('sort')->orderBy('name');
    }

    public function columns(): array
    {
        return [
            'Naam' => fn (UnitTerm $term) => $term->name,
            'Soort' => fn (UnitTerm $term) => UnitTerm::TAXONOMIES[$term->taxonomy]['label'] ?? $term->taxonomy,
            'Slug' => fn (UnitTerm $term) => $term->slug,
            'Units' => fn (UnitTerm $term) => $term->units_count,
        ];
    }

    public function title(Model $record): string
    {
        return (string) $record->name;
    }

    public function fields(?Model $record): array
    {
        return [
            'Kenmerk' => [
                Field::make('name', 'Naam')->required()->half(),
                Field::make('slug', 'Slug', 'slug')->half()->slugFrom('name'),
                Field::make('taxonomy', 'Soort', 'select')->options(collect(UnitTerm::TAXONOMIES)->map->label->all())->half(),
                Field::make('sort', 'Volgorde', 'number')->half(),
                Field::make('description', 'Omschrijving', 'textarea')->rows(3),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'taxonomy' => ['required', Rule::in(array_keys(UnitTerm::TAXONOMIES))],
            'sort' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }
}
