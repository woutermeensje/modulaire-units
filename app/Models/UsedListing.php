<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\UsedListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A used unit offered for sale by a visitor via /units-aanbieden/. New listings wait for approval
 * in the admin ("pending") before they appear on /gebruikt/.
 */
#[Fillable([
    'title', 'slug', 'description', 'location', 'price', 'images',
    'contact_name', 'contact_email', 'contact_phone', 'ip_address', 'status', 'published_at',
])]
class UsedListing extends Model
{
    /** @use HasFactory<UsedListingFactory> */
    use HasFactory, Publishable;

    public const MAX_IMAGES = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'price' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function url(): string
    {
        return SiteUrl::path(route('used-listings.show', $this->slug, false));
    }

    /**
     * "€ 12.500".
     */
    public function formattedPrice(): string
    {
        return '€ '.number_format($this->price, 0, ',', '.');
    }

    public function summary(int $words = 30): string
    {
        return Str::words(trim((string) preg_replace('/\s+/', ' ', $this->description)), $words, '…');
    }

    /**
     * @return list<array{path: string, alt: string|null}>
     */
    public function gallery(): array
    {
        return collect($this->images ?? [])
            ->map(fn (array|string $image): array => is_array($image) ? $image : ['path' => $image, 'alt' => null])
            ->values()
            ->all();
    }

    public function coverImage(): ?string
    {
        return $this->gallery()[0]['path'] ?? null;
    }

    /**
     * A slug based on the title that is not used by another listing yet.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'unit';
        $slug = $base;
        $counter = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
