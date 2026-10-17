<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'normalized_name' => fn (array $attributes): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($attributes['name']))),
        ];
    }
}
