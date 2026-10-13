<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->route('category'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => [$this->isMethod('POST') ? 'required' : 'nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('8mb')],
            'sort_order' => ['required', 'integer', 'min:0'],
            'show_on_home' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
