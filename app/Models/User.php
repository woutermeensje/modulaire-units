<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'company_name', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<UsedListing, $this>
     */
    public function usedListings(): HasMany
    {
        return $this->hasMany(UsedListing::class);
    }

    /**
     * Units an admin composed in the account's unit composer and added to the products.
     *
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Quotes sent to this user as a client; see Portal\QuoteComposerController.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'client_id');
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function firstName(): string
    {
        return filled($this->first_name) ? $this->first_name : explode(' ', trim((string) $this->name))[0];
    }
}
