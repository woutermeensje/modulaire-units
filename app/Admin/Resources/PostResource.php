<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class PostResource extends Resource
{
    public string $model = Post::class;

    public string $key = 'posts';

    public string $singular = 'Blog';

    public string $plural = 'Blogs';

    public string $icon = 'ph-newspaper-clipping';

    public ?string $description = 'Blogartikelen. De URL is /{slug}/, net als op de oude WordPress-site.';

    public function query(Builder $query): Builder
    {
        return $query->with(['author', 'category'])->latest('published_at');
    }

    public function columns(): array
    {
        return [
            'Titel' => fn (Post $post) => new HtmlString('<strong>'.e($post->title).'</strong>'),
            'Auteur' => fn (Post $post) => $post->author?->name,
            'Categorie' => fn (Post $post) => $post->category?->name,
            'Status' => fn (Post $post) => $post->status,
            'Publicatiedatum' => fn (Post $post) => $post->published_at?->format('d-m-Y'),
        ];
    }

    public function fields(?Model $record): array
    {
        return [
            'Artikel' => [
                Field::make('title', 'Titel')->required(),
                Field::make('slug', 'Slug', 'slug')->slugFrom('title'),
                Field::make('excerpt', 'Samenvatting', 'textarea')->rows(3)->help('Wordt getoond in het blogoverzicht.'),
                Field::make('content', 'Inhoud', 'richtext')->rows(16),
                Field::make('featured_image', 'Uitgelichte afbeelding', 'image')->half(),
                Field::make('featured_image_alt', 'Alt-tekst afbeelding')->half(),
            ],
            'Publicatie' => [
                Field::make('author_id', 'Auteur', 'select')->options(['' => '—'] + Author::query()->orderBy('name')->pluck('name', 'id')->all())->half(),
                Field::make('category_id', 'Categorie', 'select')->options(['' => '—'] + Category::query()->orderBy('name')->pluck('name', 'id')->all())->half(),
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
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('posts', 'slug')->ignore($record?->id)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'featured_image_upload' => ['nullable', 'image', 'max:10240'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'author_id' => ['nullable', Rule::exists('authors', 'id')],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'published_at' => ['nullable', 'date'],
        ] + $this->seoRules();
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['author_id'] = ($attributes['author_id'] ?? null) ?: null;
        $attributes['category_id'] = ($attributes['category_id'] ?? null) ?: null;
        $attributes['published_at'] = ($attributes['published_at'] ?? null) ?: (($attributes['status'] ?? null) === 'published' ? now() : null);

        return $attributes;
    }
}
