<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $name = preg_replace('/\s+/u', ' ', trim($this->input('name')));
            $this->merge(['name' => $name, 'normalized_name' => mb_strtolower($name)]);
        } else {
            $this->merge(['normalized_name' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'normalized_name' => ['required', 'string', 'max:100', Rule::unique('brands', 'normalized_name')->ignore($this->route('brand'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['normalized_name.unique' => 'Merek tersebut sudah ada di master data.'];
    }
}
