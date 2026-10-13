<?php

namespace Database\Seeders;

use App\Models\Carousel;
use Illuminate\Database\Seeder;

class CarouselSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $slides = [
            [
                'eyebrow' => '#homecinema',
                'title' => 'Sound that feels like home',
                'cta_label' => 'Explore products',
                'cta_url' => '/services',
                'image_alt' => 'Luxury home cinema room',
                'sort_order' => 1,
            ],
            [
                'eyebrow' => '#privatetheater',
                'title' => 'Every room deserves better sound',
                'cta_label' => 'View our collection',
                'cta_url' => '/services',
                'image_alt' => 'Premium private cinema with tiered seating',
                'sort_order' => 2,
            ],
            [
                'eyebrow' => '#audioexperience',
                'title' => 'Hear every detail come alive',
                'cta_label' => 'Talk to our team',
                'cta_url' => '/contact',
                'image_alt' => 'Professional home audio studio',
                'sort_order' => 3,
            ],
        ];

        foreach ($slides as $slide) {
            Carousel::query()->updateOrCreate(
                ['title' => $slide['title']],
                [...$slide, 'image_position' => 'object-center', 'is_active' => true],
            );
        }
    }
}
