<?php

namespace Database\Factories;

use App\Models\MegaMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MegaMenu>
 */
class MegaMenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->word(),
            'href' => '/collection/'.fake()->slug(2),
            'groups' => [[
                'title' => 'By Type',
                'links' => [
                    ['label' => 'Bookshelf', 'href' => '/collection/bookshelf'],
                    ['label' => 'Floorstanding', 'href' => '/collection/floorstanding'],
                ],
            ]],
            'featured_title' => fake()->sentence(5),
            'featured_url' => '/collection/all-products',
            'featured_image_path' => null,
            'featured_image_alt' => fake()->sentence(4),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
