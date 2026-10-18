<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['menu', 'parent_id', 'label', 'url', 'sort'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * Menus used by the layout. Footer menus are rendered as columns, child items as links.
     *
     * @var array<string, string>
     */
    public const MENUS = [
        'main' => 'Hoofdmenu',
        'footer' => 'Footer',
    ];

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort');
    }

    /**
     * Top-level items of a menu with their children eager loaded.
     *
     * @return Collection<int, MenuItem>
     */
    public static function tree(string $menu): Collection
    {
        return once(fn (): Collection => static::query()
            ->where('menu', $menu)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort')
            ->get());
    }

    public function isActive(string $currentPath): bool
    {
        if ($this->url === null) {
            return false;
        }

        $path = '/'.trim((string) parse_url($this->url, PHP_URL_PATH), '/');

        return $path === '/'.trim($currentPath, '/');
    }
}
