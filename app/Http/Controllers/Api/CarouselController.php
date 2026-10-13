<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarouselResource;
use App\Models\Carousel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarouselController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $carousels = Carousel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CarouselResource::collection($carousels);
    }
}
