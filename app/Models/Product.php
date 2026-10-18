<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'wp_id', 'title', 'slug', 'short_description', 'content', 'featured_image', 'featured_image_alt', 'gallery', 'price',
    'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'sort', 'published_at',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, Publishable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function url(): string
    {
        return SiteUrl::path(route('products.show', $this->slug, false));
    }

    /**
     * @return list<array{path: string, alt: string|null}>
     */
    public function images(): array
    {
        $images = collect($this->gallery ?? [])
            ->map(fn (array|string $image): array => is_array($image) ? $image : ['path' => $image, 'alt' => null]);

        if ($this->featured_image && ! $images->contains('path', $this->featured_image)) {
            $images->prepend(['path' => $this->featured_image, 'alt' => $this->featured_image_alt]);
        }

        return $images->unique('path')->values()->all();
    }
}
