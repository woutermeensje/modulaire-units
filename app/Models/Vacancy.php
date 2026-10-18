<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use App\Support\SiteUrl;
use Database\Factories\VacancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'wp_id', 'title', 'slug', 'content', 'location', 'employment_type', 'featured_image', 'is_filled',
    'meta_title', 'meta_description', 'og_image', 'noindex', 'status', 'published_at',
])]
class Vacancy extends Model
{
    /** @use HasFactory<VacancyFactory> */
    use HasFactory, Publishable;

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
        return SiteUrl::path(route('vacancies.show', $this->slug, false));
    }
}
