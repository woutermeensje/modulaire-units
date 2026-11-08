<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\VacancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'translations', 'wp_id', 'title', 'slug', 'content', 'location', 'employment_type', 'featured_image', 'is_filled',
    'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'published_at',
])]
class Vacancy extends Model
{
    /** @use HasFactory<VacancyFactory> */
    use HasFactory, HasTranslations, Publishable;

    /**
     * Attributes translated for the other languages of the website; the first one decides whether a record is translated.
     *
     * @var list<string>
     */
    public const TRANSLATABLE = ['title', 'slug', 'content', 'location', 'employment_type', 'meta_title', 'meta_description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_filled' => 'boolean',
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function url(): string
    {
        return SiteUrl::route('vacancies.show', $this->slug, $this->urlLocale());
    }
}
