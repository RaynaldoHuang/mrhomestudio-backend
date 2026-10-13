<?php

use App\Http\Controllers\Api\Admin\CarouselController as AdminCarouselController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\MegaMenuController as AdminMegaMenuController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarouselController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MegaMenuController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('carousels', [CarouselController::class, 'index'])
    ->name('carousels.index');

Route::get('mega-menus', [MegaMenuController::class, 'index'])
    ->name('mega-menus.index');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

Route::apiResource('products', ProductController::class)
    ->only(['index', 'show'])
    ->names('products');

Route::middleware('super.admin')->group(function (): void {
    Route::apiResource('admin/categories', AdminCategoryController::class)->except('show')->names('admin.categories');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::patch('auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
    Route::put('auth/password', [AuthController::class, 'updatePassword'])->name('auth.password.update');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::apiResource('admin/carousels', AdminCarouselController::class)
        ->except('show')
        ->names('admin.carousels');

    Route::apiResource('admin/mega-menus', AdminMegaMenuController::class)
        ->names('admin.mega-menus');

    Route::apiResource('admin/products', AdminProductController::class)
        ->names('admin.products');
});
