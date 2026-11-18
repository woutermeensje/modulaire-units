<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\User;
use App\Support\UnitConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'created_by' => User::factory()->admin(),
            'title' => 'Offerte '.fake()->words(2, true),
            'intro' => fake()->paragraph(),
            'configuration' => UnitConfiguration::normalize(UnitConfiguration::STARTER),
            'images' => null,
            'sent_at' => now(),
        ];
    }

    /**
     * Saved but not sent yet.
     */
    public function draft(): static
    {
        return $this->state(fn (): array => ['sent_at' => null]);
    }
}
