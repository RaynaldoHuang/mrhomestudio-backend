<?php

namespace App\Models;

use Database\Factories\CarouselFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'eyebrow',
    'title',
    'cta_label',
    'cta_url',
    'image_path',
    'image_alt',
    'image_position',
    'sort_order',
    'is_active',
])]
class Carousel extends Model
{
    /** @use HasFactory<CarouselFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
