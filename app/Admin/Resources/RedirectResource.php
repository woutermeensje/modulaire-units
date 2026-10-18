<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class RedirectResource extends Resource
{
    public string $model = Redirect::class;

    public string $key = 'redirects';

    public string $singular = 'Redirect';

    public string $plural = 'Redirects';

    public string $icon = 'ph-arrow-bend-up-right';

    public ?string $description = 'Doorverwijzingen van oude URL\'s (bijv. van de WordPress-site) naar nieuwe pagina\'s.';

    public array $searchable = ['from_path', 'to_path'];

    public int $perPage = 100;

    public function query(Builder $query): Builder
    {
        return $query->orderBy('from_path');
    }

    public function columns(): array
    {
        return [
            'Van' => fn (Redirect $redirect) => $redirect->from_path,
            'Naar' => fn (Redirect $redirect) => $redirect->to_path,
            'Type' => fn (Redirect $redirect) => $redirect->status_code,
            'Gebruikt' => fn (Redirect $redirect) => $redirect->hits.'×',
        ];
    }

    public function title(Model $record): string
    {
        return (string) $record->from_path;
    }

    public function publicUrl(Model $record): ?string
    {
        return $record->from_path.'/';
    }

    public function fields(?Model $record): array
    {
        return [
            'Redirect' => [
                Field::make('from_path', 'Oude URL (pad)')->required()->half()->placeholder('/oude-pagina/'),
                Field::make('to_path', 'Nieuwe URL')->required()->half()->placeholder('/nieuwe-pagina/'),
                Field::make('status_code', 'Type', 'select')->options(['301' => '301 — permanent', '302' => '302 — tijdelijk'])->half(),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'from_path' => ['required', 'string', 'max:255', Rule::unique('redirects', 'from_path')->ignore($record?->id)],
            'to_path' => ['required', 'string', 'max:255'],
            'status_code' => ['required', Rule::in(['301', '302'])],
        ];
    }
}
