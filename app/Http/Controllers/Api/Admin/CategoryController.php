<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\ImageUpload;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->withCount('products')->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(StoreCategoryRequest $request, ImageUpload $imageUpload): JsonResponse
    {
        $attributes = $request->safe()->except('image');
        $baseSlug = Str::slug($attributes['name']) ?: 'category';
        if ($baseSlug === 'all-products') {
            $baseSlug = 'category-all-products';
        }
        $slug = $baseSlug;
        $suffix = 2;
        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }
        $attributes['slug'] = $slug;
        $attributes['image_path'] = $imageUpload->store($request->file('image'), 'categories', 1600, 'image');
        $category = Category::query()->create($attributes);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category, ImageUpload $imageUpload): CategoryResource
    {
        if ($request->boolean('show_on_home') && ! $request->hasFile('image') && ! $category->image_path) {
            throw ValidationException::withMessages(['image' => ['Upload a cover image before displaying this category on the homepage.']]);
        }
        $attributes = $request->safe()->except('image');
        $previousImage = $category->image_path;
        if ($request->hasFile('image')) {
            $attributes['image_path'] = $imageUpload->store($request->file('image'), 'categories', 1600, 'image');
        }
        $category->update($attributes);
        if ($request->hasFile('image') && $previousImage) {
            Storage::disk('public')->delete($previousImage);
        }

        return new CategoryResource($category->refresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages(['category' => ['Move products to another category before deleting this category.']]);
        }
        $image = $category->image_path;
        $category->delete();
        if ($image) {
            Storage::disk('public')->delete($image);
        }

        return response()->json(status: 204);
    }
}
