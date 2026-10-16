<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMegaMenuRequest;
use App\Http\Requests\UpdateMegaMenuRequest;
use App\Http\Resources\MegaMenuResource;
use App\ImageUpload;
use App\MenuCategories;
use App\Models\MegaMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MegaMenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return MegaMenuResource::collection(
            MegaMenu::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMegaMenuRequest $request, ImageUpload $imageUpload, MenuCategories $menuCategories): JsonResponse
    {
        $attributes = $request->safe()->except('featured_image');

        if ($request->hasFile('featured_image')) {
            $attributes['featured_image_path'] = $imageUpload->store($request->file('featured_image'), 'mega-menus', 2400, 'featured_image');
        }

        try {
            $megaMenu = DB::transaction(function () use ($attributes, $menuCategories): MegaMenu {
                $attributes['groups'] = $menuCategories->link($attributes['groups']);

                return MegaMenu::query()->create($attributes);
            });
        } catch (Throwable $exception) {
            if (isset($attributes['featured_image_path'])) {
                Storage::disk('public')->delete($attributes['featured_image_path']);
            }
            throw $exception;
        }

        return (new MegaMenuResource($megaMenu))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MegaMenu $megaMenu): MegaMenuResource
    {
        return new MegaMenuResource($megaMenu);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMegaMenuRequest $request, MegaMenu $megaMenu, ImageUpload $imageUpload, MenuCategories $menuCategories): MegaMenuResource
    {
        $attributes = $request->safe()->except('featured_image');
        $previousImagePath = $megaMenu->featured_image_path;

        if ($request->hasFile('featured_image')) {
            $attributes['featured_image_path'] = $imageUpload->store($request->file('featured_image'), 'mega-menus', 2400, 'featured_image');
        }

        try {
            DB::transaction(function () use ($attributes, $megaMenu, $menuCategories): void {
                $attributes['groups'] = $menuCategories->link($attributes['groups'], $megaMenu);
                $megaMenu->update($attributes);
            });
        } catch (Throwable $exception) {
            if (isset($attributes['featured_image_path'])) {
                Storage::disk('public')->delete($attributes['featured_image_path']);
            }
            throw $exception;
        }

        if ($request->hasFile('featured_image') && $previousImagePath !== null) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return new MegaMenuResource($megaMenu->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MegaMenu $megaMenu): JsonResponse
    {
        if ($megaMenu->featured_image_path !== null) {
            Storage::disk('public')->delete($megaMenu->featured_image_path);
        }

        $megaMenu->delete();

        return response()->json(status: 204);
    }
}
