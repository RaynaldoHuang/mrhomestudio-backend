<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->productCategory?->name ?? $this->category,
            'category_id' => $this->category_id,
            'category_slug' => $this->productCategory?->slug,
            'brand' => $this->productBrand?->name,
            'brand_id' => $this->brand_id,
            'price' => $this->price,
            'description' => $this->description,
            'specifications' => $this->specifications ?? [],
            'variants' => $this->variants ?? [],
            'is_active' => $this->is_active,
            'show_on_home' => $this->show_on_home,
            'images' => $this->whenLoaded('images', fn (): array => $this->images->map(fn ($image): array => [
                'id' => $image->id,
                'url' => Storage::disk('public')->url($image->path),
                'sort_order' => $image->sort_order,
            ])->all()),
            'thumbnail_url' => $this->whenLoaded('images', fn (): ?string => $this->images->first() === null
                ? null
                : Storage::disk('public')->url($this->images->first()->path)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
