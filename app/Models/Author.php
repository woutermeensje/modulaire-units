<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\SiteUrl;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['translations', 'wp_id', 'name', 'slug', 'bio'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, HasTranslations;

    /**
     * Attributes translated for the other languages of the website; the first one decides whether a record is translated.
     *
     * @var list<string>
     */
    public const TRANSLATABLE = ['name', 'slug', 'bio'];

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function url(): string
    {
        return SiteUrl::path(route('blog.author', $this->slug, false));
    }
}
