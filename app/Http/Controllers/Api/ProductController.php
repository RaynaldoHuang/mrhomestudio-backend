<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $limit = min(max(request()->integer('limit', 100), 1), 100);

        return ProductResource::collection(
            Product::query()
                ->with(['images', 'productCategory'])
                ->where('is_active', true)
                ->when(request()->filled('category_id'), fn ($query) => $query->where('category_id', request()->integer('category_id')))
                ->when(request()->boolean('show_on_home'), fn ($query) => $query->where('show_on_home', true))
                ->latest()
                ->limit($limit)
                ->get(),
        );
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active, 404);

        return new ProductResource($product->load(['images', 'productCategory']));
    }
}
