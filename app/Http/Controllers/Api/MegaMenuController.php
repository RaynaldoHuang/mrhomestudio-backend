<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MegaMenuResource;
use App\Models\Category;
use App\Models\MegaMenu;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MegaMenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $activeCategoryIds = Category::query()->where('is_active', true)->pluck('id')->all();

        return MegaMenuResource::collection(
            MegaMenu::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()->each(function (MegaMenu $menu) use ($activeCategoryIds): void {
                    $menu->groups = array_map(function (array $group) use ($activeCategoryIds): array {
                        $group['links'] = array_values(array_filter($group['links'], fn (array $link): bool => ! isset($link['category_id']) || in_array((int) $link['category_id'], $activeCategoryIds, true)));

                        return $group;
                    }, $menu->groups ?? []);
                }),
        );
    }
}
