<?php

namespace Database\Factories;

use App\Models\UnitTerm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnitTerm>
 */
class UnitTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taxonomy' => UnitTerm::TYPE,
            'name' => $name = fake()->unique()->word(),
            'slug' => Str::slug($name),
        ];
    }
}
