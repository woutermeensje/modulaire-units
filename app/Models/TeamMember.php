<?php

namespace App\Models;

use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['wp_id', 'name', 'role', 'email', 'phone', 'linkedin_url', 'photo', 'sort', 'is_visible'])]
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()
            ->reject(fn (string $part): bool => in_array(Str::lower($part), ['van', 'der', 'de', 'den', 'het', 'ter'], true))
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
