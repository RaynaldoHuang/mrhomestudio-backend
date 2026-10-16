<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreMegaMenuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'href' => ['required', 'string', 'max:2048'],
            'groups' => ['required', 'array', 'min:1', 'max:3'],
            'groups.*.title' => ['required', 'string', 'max:100'],
            'groups.*.links' => ['required', 'array', 'min:1', 'max:12'],
            'groups.*.links.*.label' => ['required', 'string', 'max:100'],
            'groups.*.links.*.category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'groups.*.links.*.href' => ['required', 'string', 'max:2048'],
            'featured_title' => ['nullable', 'string', 'max:255'],
            'featured_url' => ['nullable', 'string', 'max:2048'],
            'featured_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('8mb')],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
