<?php

namespace App\Admin\Resources;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everyone who registered an account on the platform (customers only, no CMS admins).
 */
class ClientResource extends UserResource
{
    public string $key = 'opdrachtgevers';

    public string $singular = 'Opdrachtgever';

    public string $plural = 'Opdrachtgevers';

    public string $icon = 'ph-address-book';

    public ?string $description = 'Iedereen die een account heeft aangemaakt op het platform, nieuwste eerst.';

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
            'Geregistreerd' => fn (User $user) => $user->created_at?->format('d-m-Y H:i'),
        ];
    }
}
