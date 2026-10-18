<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Media;
use App\Models\Product;
use App\Support\SiteUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class ProductResource extends Resource
{
    public string $model = Product::class;

    public string $key = 'products';

    public string $singular = 'Product';

    public string $plural = 'Producten';

    public string $icon = 'ph-package';

    public ?string $description = 'De voormalige WooCommerce-producten (/winkel/). Bezoekers voegen ze toe aan "Mijn offerte".';

    public function query(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('title');
    }

    public function columns(): array
    {
        return [
            'Foto' => fn (Product $product) => $product->featured_image
                ? new HtmlString('<img class="portal-thumb" src="'.e(SiteUrl::media(Media::variant($product->featured_image, 'thumbnail'))).'" alt="">')
                : '',
            'Titel' => fn (Product $product) => new HtmlString('<strong>'.e($product->title).'</strong>'),
            'Prijs' => fn (Product $product) => $product->price,
            'Status' => fn (Product $product) => $product->status,
        ];
    }

    public function fields(?Model $record): array
    {
        return [
            'Product' => [
                Field::make('title', 'Titel')->required(),
                Field::make('slug', 'Slug', 'slug')->slugFrom('title'),
                Field::make('price', 'Prijs (tekst)')->half()->placeholder('Bijv. € 2.950,- excl. btw'),
                Field::make('sort', 'Volgorde', 'number')->half(),
                Field::make('short_description', 'Korte omschrijving', 'richtext')->rows(4),
                Field::make('content', 'Beschrijving', 'richtext')->rows(14),
            ],
            "Foto's" => [
                Field::make('featured_image', 'Hoofdfoto', 'image')->half(),
                Field::make('featured_image_alt', 'Alt-tekst hoofdfoto')->half(),
                Field::make('gallery', "Extra foto's", 'gallery'),
            ],
            'Publicatie' => [
                Field::make('status', 'Status', 'select')->options($this->statusOptions())->half(),
            ],
            'SEO' => $this->seoFields(),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('products', 'slug')->ignore($record?->id)],
            'price' => ['nullable', 'string', 'max:190'],
            'sort' => ['nullable', 'integer'],
            'short_description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'featured_image_upload' => ['nullable', 'image', 'max:10240'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'gallery_upload.*' => ['image', 'max:10240'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
        ] + $this->seoRules();
    }
}
