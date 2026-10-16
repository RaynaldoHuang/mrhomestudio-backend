<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\MenuCategories;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(MenuCategories $menuCategories): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->whereIn('id', array_keys($menuCategories->references()))->where('is_active', true)
                ->when(request()->boolean('show_on_home'), fn ($query) => $query->where('show_on_home', true)->whereNotNull('image_path'))
                ->orderBy('sort_order')->orderBy('id')->get(),
        );
    }
}
