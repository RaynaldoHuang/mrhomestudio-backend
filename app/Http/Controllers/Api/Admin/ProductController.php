<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()->with(['images', 'productCategory'])->latest()->get(),
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $attributes = $request->safe()->except('images');
        if (array_key_exists('category_id', $attributes)) {
            $attributes['category'] = isset($attributes['category_id']) ? Category::query()->findOrFail($attributes['category_id'])->name : null;
        }
        $attributes['slug'] = $this->uniqueSlug($attributes['name']);

        $product = DB::transaction(function () use ($request, $attributes): Product {
            $product = Product::query()->create($attributes);

            foreach ($request->file('images', []) as $sortOrder => $image) {
                $product->images()->create([
                    'path' => $image->store('products', 'public'),
                    'sort_order' => $sortOrder,
                ]);
            }

            return $product;
        });

        return (new ProductResource($product->load(['images', 'productCategory'])))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['images', 'productCategory']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $removeImageIds = $request->validated('remove_image_ids', []);
        $imagesToRemove = $product->images()->whereIn('id', $removeImageIds)->get();
        $remainingImageCount = $product->images()->count() - $imagesToRemove->count();
        $newImageCount = count($request->file('images', []));

        if ($remainingImageCount + $newImageCount < 1 || $remainingImageCount + $newImageCount > 5) {
            throw ValidationException::withMessages([
                'images' => ['A product must have between 1 and 5 images.'],
            ]);
        }

        $attributes = $request->safe()->except(['images', 'remove_image_ids']);
        if (array_key_exists('category_id', $attributes)) {
            $attributes['category'] = isset($attributes['category_id']) ? Category::query()->findOrFail($attributes['category_id'])->name : null;
        }
        if ($attributes['name'] !== $product->name) {
            $attributes['slug'] = $this->uniqueSlug($attributes['name'], $product);
        }

        DB::transaction(function () use ($request, $product, $attributes, $imagesToRemove): void {
            $product->update($attributes);
            $imagesToRemove->each->delete();

            $nextSortOrder = (int) ($product->images()->max('sort_order') ?? -1) + 1;
            foreach ($request->file('images', []) as $index => $image) {
                $product->images()->create([
                    'path' => $image->store('products', 'public'),
                    'sort_order' => $nextSortOrder + $index,
                ]);
            }

            $product->images()->orderBy('sort_order')->orderBy('id')->get()
                ->each(fn ($image, $index) => $image->update(['sort_order' => $index]));
        });

        foreach ($imagesToRemove as $image) {
            Storage::disk('public')->delete($image->path);
        }

        return new ProductResource($product->refresh()->load(['images', 'productCategory']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $paths = $product->images()->pluck('path');
        $product->delete();
        Storage::disk('public')->delete($paths->all());

        return response()->json(status: 204);
    }

    private function uniqueSlug(string $name, ?Product $ignoredProduct = null): string
    {
        $baseSlug = Str::slug($name) ?: 'product';
        $slug = $baseSlug;
        $suffix = 2;

        while (Product::query()
            ->when($ignoredProduct, fn ($query) => $query->whereKeyNot($ignoredProduct->getKey()))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
