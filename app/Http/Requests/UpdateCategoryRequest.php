<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\File;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('8mb')],
        ];
    }
}
