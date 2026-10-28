<?php

namespace App\Http\Requests;

use App\ProductVariantPricing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
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
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['required', 'string'],
            'specifications' => ['nullable', 'array'],
            'specifications.*.name' => ['required', 'string', 'max:100'],
            'specifications.*.value' => ['required', 'string', 'max:500'],
            'variants' => ['nullable', 'array', 'list'],
            'variants.*.name' => ['required', 'string', 'max:100', 'distinct:strict'],
            'variants.*.values' => ['required', 'array', 'list', 'min:1'],
            'variants.*.values.*' => ['required', 'string', 'max:100'],
            'variant_prices' => ['nullable', 'array', 'list', 'max:500'],
            'variant_prices.*' => ['required', 'array:options,price'],
            'variant_prices.*.options' => ['required', 'array', 'list'],
            'variant_prices.*.options.*' => ['required', 'string', 'max:100'],
            'variant_prices.*.price' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('8mb')],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'distinct'],
            'is_active' => ['required', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            ProductVariantPricing::validate($validator, $this->input('variants') ?? [], $this->input('variant_prices') ?? []);
        }];
    }
}
