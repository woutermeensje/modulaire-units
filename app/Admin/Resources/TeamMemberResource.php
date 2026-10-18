<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamMemberResource extends Resource
{
    public string $model = TeamMember::class;

    public string $key = 'team-members';

    public string $singular = 'Teamlid';

    public string $plural = 'Team';

    public string $icon = 'ph-users-three';

    public ?string $description = 'Teamleden op de pagina "Ons Team" ([team] blok).';

    public array $searchable = ['name', 'role'];

    public function query(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }

    public function columns(): array
    {
        return [
            'Naam' => fn (TeamMember $member) => $member->name,
            'Functie' => fn (TeamMember $member) => $member->role,
            'Zichtbaar' => fn (TeamMember $member) => $member->is_visible ? 'Ja' : 'Nee',
            'Volgorde' => fn (TeamMember $member) => $member->sort,
        ];
    }

    public function publicUrl(Model $record): ?string
    {
        return '/ons-team/';
    }

    public function fields(?Model $record): array
    {
        return [
            'Teamlid' => [
                Field::make('name', 'Naam')->required()->half(),
                Field::make('role', 'Functie')->half(),
                Field::make('email', 'E-mailadres')->half(),
                Field::make('phone', 'Telefoonnummer')->half(),
                Field::make('linkedin_url', 'LinkedIn-URL')->half(),
                Field::make('sort', 'Volgorde', 'number')->half(),
                Field::make('photo', 'Foto', 'image')->half(),
                Field::make('is_visible', 'Tonen op de website', 'checkbox')->half(),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'role' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'sort' => ['nullable', 'integer'],
            'photo_upload' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
