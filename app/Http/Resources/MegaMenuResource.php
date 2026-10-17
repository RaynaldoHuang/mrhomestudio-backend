<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MegaMenuResource extends JsonResource
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
            'label' => $this->label,
            'href' => $this->href,
            'groups' => $this->groups,
            'display_mode' => $this->display_mode ?? 'grouped',
            'featured_title' => $this->featured_title,
            'featured_url' => $this->featured_url,
            'featured_image_url' => $this->featured_image_path === null
                ? null
                : Storage::disk('public')->url($this->featured_image_path),
            'featured_image_alt' => $this->featured_image_alt,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
