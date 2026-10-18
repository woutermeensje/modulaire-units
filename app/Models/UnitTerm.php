<?php

namespace App\Models;

use App\Support\SiteUrl;
use Database\Factories\UnitTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['wp_id', 'taxonomy', 'name', 'slug', 'description', 'sort'])]
class UnitTerm extends Model
{
    /** @use HasFactory<UnitTermFactory> */
    use HasFactory;

    public const TYPE = 'type';

    public const CONDITION = 'conditie';

    public const OFFER = 'aanbod';

    /**
     * Taxonomy key => [label, URL prefix of the original WordPress archive].
     *
     * @var array<string, array{label: string, prefix: string}>
     */
    public const TAXONOMIES = [
        self::TYPE => ['label' => 'Type unit', 'prefix' => 'unit-type'],
        self::CONDITION => ['label' => 'Conditie', 'prefix' => 'conditie'],
        self::OFFER => ['label' => 'Aanbod', 'prefix' => 'aanbod'],
    ];

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    #[Scope]
    protected function inTaxonomy(Builder $query, string $taxonomy): void
    {
        $query->where('taxonomy', $taxonomy);
    }

    public function url(): string
    {
        return SiteUrl::path(self::TAXONOMIES[$this->taxonomy]['prefix'].'/'.$this->slug);
    }

    public static function taxonomyForPrefix(string $prefix): ?string
    {
        foreach (self::TAXONOMIES as $taxonomy => $config) {
            if ($config['prefix'] === $prefix) {
                return $taxonomy;
            }
        }

        return null;
    }
}
