<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $title = fake()->unique()->words(3, true),
            'slug' => Str::slug($title),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ];
    }
}
