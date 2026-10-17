<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::query()->withCount('products')->orderBy('name')->get());
    }

    public function store(SaveBrandRequest $request): JsonResponse
    {
        $brand = Brand::query()->create($request->validated());

        return (new BrandResource($brand->loadCount('products')))->response()->setStatusCode(201);
    }

    public function update(SaveBrandRequest $request, Brand $brand): BrandResource
    {
        $brand->update($request->validated());

        return new BrandResource($brand->refresh()->loadCount('products'));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages(['brand' => ['Pindahkan atau hapus merek dari produk terkait sebelum menghapus merek ini.']]);
        }
        $brand->delete();

        return response()->json(status: 204);
    }
}
