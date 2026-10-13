<?php

namespace Database\Factories;

use App\Models\Carousel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Carousel>
 */
class CarouselFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eyebrow' => '#'.fake()->word(),
            'title' => fake()->sentence(5),
            'cta_label' => 'Explore products',
            'cta_url' => '/services',
            'image_path' => null,
            'image_alt' => fake()->sentence(4),
            'image_position' => 'object-center',
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
