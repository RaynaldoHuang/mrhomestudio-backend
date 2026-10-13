<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name), 'slug' => (string) str($name)->slug(),
            'description' => fake()->sentence(), 'image_path' => 'categories/cover.jpg',
            'sort_order' => 0, 'show_on_home' => false, 'is_active' => true,
        ];
    }
}
