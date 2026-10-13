<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarouselRequest;
use App\Http\Requests\UpdateCarouselRequest;
use App\Http\Resources\CarouselResource;
use App\Models\Carousel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CarouselController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return CarouselResource::collection(
            Carousel::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCarouselRequest $request): JsonResponse
    {
        $attributes = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $request->file('image')->store('carousels', 'public');
        }

        $carousel = Carousel::query()->create($attributes);

        return (new CarouselResource($carousel))->response()->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCarouselRequest $request, Carousel $carousel): CarouselResource
    {
        $attributes = $request->safe()->except('image');
        $previousImagePath = $carousel->image_path;

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $request->file('image')->store('carousels', 'public');
        }

        $carousel->update($attributes);

        if ($request->hasFile('image') && $previousImagePath !== null) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return new CarouselResource($carousel->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Carousel $carousel): JsonResponse
    {
        if ($carousel->image_path !== null) {
            Storage::disk('public')->delete($carousel->image_path);
        }

        $carousel->delete();

        return response()->json(status: 204);
    }
}
