<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MegaMenuResource;
use App\Models\MegaMenu;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MegaMenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return MegaMenuResource::collection(
            MegaMenu::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        );
    }
}
