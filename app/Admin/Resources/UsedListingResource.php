<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Media;
use App\Models\UsedListing;
use App\Support\SiteUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class UsedListingResource extends Resource
{
    public string $model = UsedListing::class;

    public string $key = 'used-listings';

    public string $singular = 'Gebruikte unit';

    public string $plural = 'Gebruikte units';

    public string $icon = 'ph-recycle';

    public ?string $description = 'Gebruikte units die bezoekers via "Units aanbieden" plaatsen. Nieuwe aanbiedingen staan op "Ter beoordeling" en verschijnen op /gebruikt/ zodra ze gepubliceerd zijn.';

    public function query(Builder $query): Builder
    {
        return $query->orderByRaw("status = 'pending' desc")->latest();
    }

    public function columns(): array
    {
        return [
            'Foto' => fn (UsedListing $listing) => $listing->coverImage()
                ? new HtmlString('<img class="portal-thumb" src="'.e(SiteUrl::media(Media::variant($listing->coverImage(), 'thumbnail'))).'" alt="">')
                : '',
            'Titel' => fn (UsedListing $listing) => new HtmlString('<strong>'.e($listing->title).'</strong>'),
            'Locatie' => fn (UsedListing $listing) => $listing->location,
            'Prijs' => fn (UsedListing $listing) => $listing->formattedPrice(),
            'Aanbieder' => fn (UsedListing $listing) => $listing->contact_name,
            'Status' => fn (UsedListing $listing) => $this->statusOptions()[$listing->status] ?? $listing->status,
            'Ontvangen' => fn (UsedListing $listing) => $listing->created_at?->format('d-m-Y'),
        ];
    }

    public function fields(?Model $record): array
    {
        return [
            'Unit' => [
                Field::make('title', 'Titel')->required(),
                Field::make('slug', 'Slug', 'slug')->slugFrom('title'),
                Field::make('description', 'Beschrijving', 'textarea')->rows(10)->required(),
                Field::make('location', 'Locatie')->half()->required(),
                Field::make('price', 'Prijs (€)', 'number')->half()->required(),
                Field::make('images', "Foto's", 'gallery')->help('Maximaal '.UsedListing::MAX_IMAGES.' foto\'s; de eerste is de hoofdfoto.'),
            ],
            'Aanbieder' => [
                Field::make('contact_name', 'Naam')->required(),
                Field::make('contact_email', 'E-mailadres')->half()->required(),
                Field::make('contact_phone', 'Telefoonnummer')->half(),
            ],
            'Publicatie' => [
                Field::make('status', 'Status', 'select')->options($this->statusOptions())->half(),
                Field::make('published_at', 'Publicatiedatum', 'datetime')->half(),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('used_listings', 'slug')->ignore($record?->id)],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'images_upload' => ['array', 'max:'.UsedListing::MAX_IMAGES],
            'images_upload.*' => ['image', 'max:10240'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['images'] = array_slice($attributes['images'] ?? [], 0, UsedListing::MAX_IMAGES);
        $attributes['published_at'] = ($attributes['published_at'] ?? null) ?: (($attributes['status'] ?? null) === 'published' ? now() : null);

        return $attributes;
    }

    protected function statusOptions(): array
    {
        return ['pending' => 'Ter beoordeling', 'published' => 'Gepubliceerd', 'draft' => 'Offline'];
    }
}
