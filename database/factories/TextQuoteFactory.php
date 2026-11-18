<?php

namespace Database\Factories;

use App\Models\TextQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TextQuote>
 */
class TextQuoteFactory extends Factory
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
            'body' => '<p>'.fake()->paragraph().'</p>',
            'sent_at' => now(),
        ];
    }
}
