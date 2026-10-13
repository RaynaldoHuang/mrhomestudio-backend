<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'description' => ['required', 'string'],
            'specifications' => ['nullable', 'array'],
            'specifications.*.name' => ['required', 'string', 'max:100'],
            'specifications.*.value' => ['required', 'string', 'max:500'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.values' => ['required', 'array', 'min:1'],
            'variants.*.values.*' => ['required', 'string', 'max:100', 'distinct'],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('8mb')],
            'is_active' => ['required', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
        ];
    }
}
