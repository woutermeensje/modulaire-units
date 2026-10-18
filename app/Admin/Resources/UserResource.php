<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    public string $model = User::class;

    public string $key = 'users';

    public string $singular = 'Gebruiker';

    public string $plural = 'Gebruikers';

    public string $icon = 'ph-user-gear';

    public ?string $description = 'Klantaccounts en beheerders. Beheerders hebben toegang tot dit CMS.';

    public array $searchable = ['name', 'email', 'company_name'];

    public function columns(): array
    {
        return [
            'Naam' => fn (User $user) => $user->name,
            'E-mail' => fn (User $user) => $user->email,
            'Bedrijf' => fn (User $user) => $user->company_name,
            'Rol' => fn (User $user) => $user->is_admin ? 'Beheerder' : 'Klant',
            'Aangemaakt' => fn (User $user) => $user->created_at?->format('d-m-Y'),
        ];
    }

    public function publicUrl(Model $record): ?string
    {
        return null;
    }

    public function fields(?Model $record): array
    {
        return [
            'Gebruiker' => [
                Field::make('name', 'Naam')->required()->half(),
                Field::make('email', 'E-mailadres')->required()->half(),
                Field::make('company_name', 'Bedrijfsnaam')->half(),
                Field::make('phone', 'Telefoonnummer')->half(),
                Field::make('password', 'Wachtwoord', 'password')->half()->help($record?->exists ? 'Leeg laten om niet te wijzigen.' : 'Minimaal 8 tekens.'),
                Field::make('is_admin', 'Beheerder (toegang tot CMS)', 'checkbox')->half(),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($record?->id)],
            'company_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$record?->exists ? 'nullable' : 'required', Password::defaults()],
        ];
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);

        if ($record->is(auth()->user())) {
            $attributes['is_admin'] = true;
        }

        return $attributes;
    }
}
