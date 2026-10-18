<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class VacancyResource extends Resource
{
    public string $model = Vacancy::class;

    public string $key = 'vacancies';

    public string $singular = 'Vacature';

    public string $plural = 'Vacatures';

    public string $icon = 'ph-briefcase';

    public ?string $description = 'Vacatures voor "Werken bij". Sollicitaties komen binnen onder Inzendingen.';

    public function query(Builder $query): Builder
    {
        return $query->latest('published_at');
    }

    public function columns(): array
    {
        return [
            'Titel' => fn (Vacancy $vacancy) => new HtmlString('<strong>'.e($vacancy->title).'</strong>'),
            'Locatie' => fn (Vacancy $vacancy) => $vacancy->location,
            'Vervuld' => fn (Vacancy $vacancy) => $vacancy->is_filled ? 'Ja' : 'Nee',
            'Status' => fn (Vacancy $vacancy) => $vacancy->status,
            'Geplaatst' => fn (Vacancy $vacancy) => $vacancy->published_at?->format('d-m-Y'),
        ];
    }

    public function fields(?Model $record): array
    {
        return [
            'Vacature' => [
                Field::make('title', 'Functietitel')->required(),
                Field::make('slug', 'Slug', 'slug')->slugFrom('title'),
                Field::make('location', 'Locatie')->half(),
                Field::make('employment_type', 'Dienstverband')->half()->placeholder('Bijv. Fulltime / ZZP'),
                Field::make('content', 'Omschrijving', 'richtext')->rows(16),
                Field::make('featured_image', 'Afbeelding', 'image')->half(),
                Field::make('is_filled', 'Deze vacature is vervuld', 'checkbox')->half(),
            ],
            'Publicatie' => [
                Field::make('status', 'Status', 'select')->options($this->statusOptions())->half(),
                Field::make('published_at', 'Publicatiedatum', 'datetime')->half(),
            ],
            'SEO' => $this->seoFields(),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('vacancies', 'slug')->ignore($record?->id)],
            'location' => ['nullable', 'string', 'max:190'],
            'employment_type' => ['nullable', 'string', 'max:190'],
            'content' => ['nullable', 'string'],
            'featured_image_upload' => ['nullable', 'image', 'max:10240'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'published_at' => ['nullable', 'date'],
        ] + $this->seoRules();
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['published_at'] = ($attributes['published_at'] ?? null) ?: (($attributes['status'] ?? null) === 'published' ? now() : null);

        return $attributes;
    }
}
