<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'translations', 'wp_id', 'author_id', 'category_id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'featured_image_alt',
    'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'published_at',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasTranslations, Publishable;

    /**
     * Attributes translated for the other languages of the website; the first one decides whether a record is translated.
     *
     * @var list<string>
     */
    public const TRANSLATABLE = ['title', 'slug', 'excerpt', 'content', 'featured_image_alt', 'meta_title', 'meta_description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Blog posts live in the root of the site, exactly like the WordPress permalinks.
     */
    public function url(): string
    {
        return SiteUrl::path($this->slug);
    }

    public function summary(int $words = 55): string
    {
        $text = $this->excerpt ?: strip_tags((string) $this->content);

        return Str::words(trim(html_entity_decode($text)), $words, ' […]');
    }
}
