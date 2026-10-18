<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuItemResource extends Resource
{
    public string $model = MenuItem::class;

    public string $key = 'menu-items';

    public string $singular = 'Menu-item';

    public string $plural = "Menu's";

    public string $icon = 'ph-list';

    public ?string $description = 'Hoofdmenu en footer. In de footer zijn de items op het hoogste niveau de kolomkoppen.';

    public array $searchable = ['label', 'url'];

    public int $perPage = 200;

    public function query(Builder $query): Builder
    {
        return $query->with('parent')->orderBy('menu')
            ->orderByRaw('coalesce((select sort from menu_items p where p.id = menu_items.parent_id), sort)')
            ->orderByRaw('parent_id is not null')
            ->orderBy('sort');
    }

    public function columns(): array
    {
        return [
            'Label' => fn (MenuItem $item) => ($item->parent_id ? '— ' : '').$item->label,
            'URL' => fn (MenuItem $item) => $item->url,
            'Menu' => fn (MenuItem $item) => MenuItem::MENUS[$item->menu] ?? $item->menu,
            'Volgorde' => fn (MenuItem $item) => $item->sort,
        ];
    }

    public function title(Model $record): string
    {
        return (string) $record->label;
    }

    public function publicUrl(Model $record): ?string
    {
        return $record->url;
    }

    public function fields(?Model $record): array
    {
        $parents = MenuItem::query()->whereNull('parent_id')->when($record?->exists, fn ($q) => $q->whereKeyNot($record->id))
            ->orderBy('menu')->orderBy('sort')->get()
            ->mapWithKeys(fn (MenuItem $item): array => [$item->id => (MenuItem::MENUS[$item->menu] ?? $item->menu).' › '.$item->label])
            ->prepend('— Geen (hoofdniveau) —', '')
            ->all();

        return [
            'Menu-item' => [
                Field::make('label', 'Label')->required()->half(),
                Field::make('url', 'URL')->half()->placeholder('/kopen/ of tel:0852392040')->help('Laat leeg voor een kopje zonder link.'),
                Field::make('menu', 'Menu', 'select')->options(MenuItem::MENUS)->half(),
                Field::make('parent_id', 'Onder item', 'select')->options($parents)->half(),
                Field::make('sort', 'Volgorde', 'number')->half(),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'label' => ['required', 'string', 'max:190'],
            'url' => ['nullable', 'string', 'max:255'],
            'menu' => ['required', Rule::in(array_keys(MenuItem::MENUS))],
            'parent_id' => ['nullable', Rule::exists('menu_items', 'id')],
            'sort' => ['nullable', 'integer'],
        ];
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['parent_id'] = ($attributes['parent_id'] ?? null) ?: null;
        $attributes['sort'] = (int) ($attributes['sort'] ?? 0);

        return $attributes;
    }
}
