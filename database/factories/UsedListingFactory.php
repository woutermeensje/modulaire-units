<?php

namespace Database\Factories;

use App\Models\UsedListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsedListing>
 */
class UsedListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $title = 'Gebruikte kantoorunit '.fake()->unique()->numberBetween(1, 99999),
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'price' => fake()->numberBetween(2500, 40000),
            'images' => [],
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * A listing that has been submitted but not yet approved.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => 'pending', 'published_at' => null]);
    }
}
