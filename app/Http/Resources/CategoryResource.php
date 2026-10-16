<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'menu_groups' => $this->menu_groups ?? [],
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'description' => $this->description ?? '',
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'sort_order' => $this->sort_order, 'show_on_home' => $this->show_on_home,
            'is_active' => $this->is_active, 'products_count' => $this->whenCounted('products'),
        ];
    }
}
