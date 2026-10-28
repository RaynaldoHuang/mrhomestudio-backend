<?php

namespace App;

use App\Models\Product;
use Illuminate\Validation\Validator;

class ProductVariantPricing
{
    /**
     * @param  array<array{name: string, values: array<string>}>  $variants
     * @param  array<array{options: array<string>, price: int}>  $prices
     */
    public static function validate(Validator $validator, mixed $variants, mixed $prices): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        foreach ($variants as $index => $variant) {
            if (count($variant['values']) !== count(array_unique($variant['values'], SORT_STRING))) {
                $validator->errors()->add("variants.{$index}.values", 'Each variant must have unique values.');
            }
        }

        $seen = [];
        foreach ($prices as $index => $row) {
            $valid = count($variants) > 0 && count($row['options']) === count($variants);
            foreach ($variants as $variantIndex => $variant) {
                if (! in_array($row['options'][$variantIndex] ?? null, $variant['values'], true)) {
                    $valid = false;
                }
            }
            $key = json_encode($row['options']);
            if (! $valid || isset($seen[$key])) {
                $validator->errors()->add("variant_prices.{$index}.options", 'Choose a valid, unique combination of variant values.');
            }
            $seen[$key] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(array $attributes): array
    {
        if (array_key_exists('variant_prices', $attributes)) {
            $attributes['variant_prices'] = array_map(fn (array $row): array => [
                'options' => $row['options'],
                'price' => (int) $row['price'],
            ], $attributes['variant_prices'] ?? []);
        }

        return $attributes;
    }

    /** @return array{min: int, max: int} */
    public static function range(Product $product): array
    {
        $prices = array_map(fn (array $row): int => (int) $row['price'], $product->variant_prices ?? []);
        $combinationCount = empty($product->variants) ? 0 : 1;
        foreach ($product->variants ?? [] as $variant) {
            $combinationCount *= count($variant['values']);
        }
        if ($prices === [] || count($prices) < $combinationCount) {
            $prices[] = $product->price;
        }

        return ['min' => min($prices), 'max' => max($prices)];
    }
}
