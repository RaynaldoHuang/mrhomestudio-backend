<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => str($name)->slug(),
            'category' => fake()->randomElement(['Speakers', 'Amplifiers', 'Headphones']),
            'price' => fake()->numberBetween(1_000_000, 50_000_000),
            'stock' => fake()->numberBetween(0, 25),
            'description' => fake()->paragraphs(2, true),
            'specifications' => [
                ['name' => 'Weight', 'value' => fake()->numberBetween(1, 30).' kg'],
            ],
            'variants' => [
                ['name' => 'Color', 'values' => ['Black', 'White']],
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
