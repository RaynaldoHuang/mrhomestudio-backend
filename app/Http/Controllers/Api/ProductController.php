<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\MenuCategories;
use App\Models\Category;
use App\Models\MegaMenu;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $limit = min(max(request()->integer('limit', 100), 1), 100);
        $categoryIds = null;
        if (request()->filled('collection') && request()->string('collection')->toString() !== 'all-products') {
            $slug = MenuCategories::collectionSlug('/collection/'.request()->string('collection')->toString());
            $category = $slug === null ? null : Category::query()->where('slug', $slug)->first();
            $categoryIds = [];
            if ($category?->is_active !== false && $slug !== null) {
                $menu = MegaMenu::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                    ->first(fn (MegaMenu $menu): bool => MenuCategories::collectionSlug($menu->href) === $slug);
                $categoryIds = collect($menu?->groups ?? [])->flatMap(fn (array $group): array => $group['links'])
                    ->pluck('category_id')->push($category?->id)->filter()->unique()->values()->all();
            }
        }

        return ProductResource::collection(
            Product::query()
                ->with(['images', 'productCategory'])
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('category_id')->orWhereHas('productCategory', fn ($category) => $category->where('is_active', true)))
                ->when($categoryIds !== null, fn ($query) => $query->whereIn('category_id', $categoryIds))
                ->when(request()->filled('category_id'), fn ($query) => $query->where('category_id', request()->integer('category_id')))
                ->when(request()->boolean('show_on_home'), fn ($query) => $query->where('show_on_home', true))
                ->latest()->orderByDesc('id')
                ->skip((max(request()->integer('page', 1), 1) - 1) * $limit)
                ->limit($limit)
                ->get(),
        );
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active && ($product->category_id === null || $product->productCategory?->is_active), 404);

        return new ProductResource($product->load(['images', 'productCategory']));
    }
}
