<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Everyone who registered an account on the platform (customers only, no CMS admins), and clients added here by hand.
 *
 * A client added by hand gets an account without a known password: they can't log in until they choose one with
 * "Wachtwoord vergeten". That way they stand in the same list and can be picked in the quote composer.
 */
class ClientResource extends UserResource
{
    public string $key = 'opdrachtgevers';

    public string $singular = 'Opdrachtgever';

    public string $plural = 'Opdrachtgevers';

    public string $icon = 'ph-address-book';

    public ?string $description = 'Iedereen die een account heeft aangemaakt op het platform en opdrachtgevers die je zelf hebt toegevoegd, nieuwste eerst.';

    public function query(Builder $query): Builder
    {
        return $query->where('is_admin', false)->latest();
    }

    public function columns(): array
    {
        return [
            'Naam' => fn (User $user) => $user->name,
            'Bedrijf' => fn (User $user) => $user->company_name,
            'E-mail' => fn (User $user) => $user->email,
            'Telefoon' => fn (User $user) => $user->phone,
            'Toegevoegd' => fn (User $user) => $user->created_at?->format('d-m-Y H:i'),
        ];
    }

    public function fields(?Model $record): array
    {
        return [
            'Opdrachtgever' => [
                Field::make('first_name', 'Voornaam')->required()->half(),
                Field::make('last_name', 'Achternaam')->half(),
                Field::make('company_name', 'Bedrijfsnaam')->half(),
                Field::make('email', 'E-mailadres')->required()->half(),
                Field::make('phone', 'Telefoonnummer')->half()->help('Er wordt geen account-mail verstuurd. Wie zelf wil inloggen, kiest een wachtwoord via "Wachtwoord vergeten".'),
            ],
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'first_name' => ['required', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($record?->id)],
            'company_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Accounts from the registration only have a full name; it is split into first and last name for the form.
     */
    public function fieldValue(Model $record, Field $field): mixed
    {
        if (in_array($field->name, ['first_name', 'last_name'], true) && $record->exists && blank($record->first_name)) {
            [$first, $last] = array_pad(explode(' ', trim((string) $record->name), 2), 2, null);

            return old($field->name, $field->name === 'first_name' ? $first : $last);
        }

        return parent::fieldValue($record, $field);
    }

    public function fill(Model $record, array $validated, Request $request): array
    {
        $attributes = parent::fill($record, $validated, $request);
        $attributes['name'] = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));

        if (! $record->exists) {
            // Nobody knows this password; the client sets their own with "Wachtwoord vergeten".
            $attributes['password'] = Str::random(40);
            $attributes['is_admin'] = false;
        }

        return $attributes;
    }
}
