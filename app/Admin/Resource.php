<?php

namespace App\Admin;

use App\Services\MediaUploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Describes how a model is listed and edited in the admin. The generic ResourceController
 * and the admin/resource views do the rest.
 */
abstract class Resource
{
    /** @var class-string<Model> */
    public string $model;

    public string $key;

    public string $singular;

    public string $plural;

    public string $icon = 'ph-file';

    public ?string $description = null;

    /** @var list<string> */
    public array $searchable = ['title'];

    public int $perPage = 30;

    /**
     * Columns on the index page: label => callback(model) returning a string or HtmlString.
     *
     * @return array<string, \Closure(Model): mixed>
     */
    abstract public function columns(): array;

    /**
     * Form layout: section title => list of fields.
     *
     * @return array<string, list<Field>>
     */
    abstract public function fields(?Model $record): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(?Model $record): array;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function query(Builder $query): Builder
    {
        return $query->latest('updated_at');
    }

    public function publicUrl(Model $record): ?string
    {
        return method_exists($record, 'url') ? $record->url() : null;
    }

    public function title(Model $record): string
    {
        return (string) ($record->title ?? $record->name ?? $record->label ?? '#'.$record->getKey());
    }

    /**
     * Turn the validated request into model attributes. Image, gallery and boolean fields are handled here.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function fill(Model $record, array $validated, Request $request): array
    {
        $uploader = app(MediaUploader::class);
        $attributes = [];

        foreach ($this->allFields($record) as $field) {
            $name = $field->name;

            match ($field->type) {
                'image' => $attributes[$name] = $request->boolean($name.'_remove')
                    ? null
                    : ($request->hasFile($name.'_upload') ? $uploader->store($request->file($name.'_upload')) : $record->{$name}),
                'gallery' => $attributes[$name] = $this->galleryValue($record, $name, $request, $uploader),
                'checkbox' => $attributes[$name] = $request->boolean($name),
                'hero' => $attributes[$name] = $this->heroValue($record, $request, $uploader),
                'terms', 'readonly' => null,
                'password' => filled($validated[$name] ?? null) ? $attributes[$name] = $validated[$name] : null,
                'slug' => $attributes[$name] = Str::slug((string) ($validated[$name] ?? '')) ?: Str::slug((string) ($validated[$field->slugSource ?? 'title'] ?? '')),
                default => array_key_exists($name, $validated) ? $attributes[$name] = $validated[$name] : null,
            };
        }

        return $attributes;
    }

    /**
     * Hook for relations that can only be saved once the record exists.
     */
    public function afterSave(Model $record, Request $request): void {}

    /**
     * @return list<Field>
     */
    public function allFields(?Model $record): array
    {
        return array_merge(...array_values($this->fields($record)));
    }

    /**
     * @return list<array{path: string, alt: string|null}>
     */
    private function galleryValue(Model $record, string $name, Request $request, MediaUploader $uploader): array
    {
        $removed = (array) $request->input($name.'_remove', []);
        $images = collect($record->{$name} ?? [])
            ->map(fn (array|string $image): array => is_array($image) ? $image : ['path' => $image, 'alt' => null])
            ->reject(fn (array $image): bool => in_array($image['path'], $removed, true));

        foreach ((array) $request->file($name.'_upload', []) as $file) {
            $images->push(['path' => $uploader->store($file), 'alt' => null]);
        }

        return $images->values()->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function heroValue(Model $record, Request $request, MediaUploader $uploader): array
    {
        $hero = Arr::only((array) $request->input('hero', []), ['eyebrow', 'title', 'subtitle', 'button_text', 'button_url']);
        $hero['image'] = $request->boolean('hero_image_remove')
            ? null
            : ($request->hasFile('hero_image_upload') ? $uploader->store($request->file('hero_image_upload')) : ($record->hero['image'] ?? null));

        return $hero;
    }

    /**
     * Shared SEO fields.
     *
     * @return list<Field>
     */
    protected function seoFields(): array
    {
        return [
            Field::make('meta_title', 'SEO-titel')->help('Leeg laten = titel + sitenaam.'),
            Field::make('meta_description', 'Meta description', 'textarea')->rows(3),
            Field::make('og_image', 'Social afbeelding', 'image')->half(),
            Field::make('noindex', 'Niet laten indexeren door zoekmachines (noindex)', 'checkbox')->half(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seoRules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image_upload' => ['nullable', 'image', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        return ['published' => 'Gepubliceerd', 'draft' => 'Concept'];
    }
}
