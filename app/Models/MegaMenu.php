<?php

namespace App\Models;

use Database\Factories\MegaMenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'label', 'href', 'groups', 'display_mode', 'featured_title', 'featured_url',
    'featured_image_path', 'featured_image_alt', 'sort_order', 'is_active',
])]
class MegaMenu extends Model
{
    /** @use HasFactory<MegaMenuFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'groups' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
