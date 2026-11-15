<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CasinoBannerResource;
use App\Models\CasinoBanner;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The casino home's promotion banners an administrator has made visible now, in their order. */
class CasinoBannerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CasinoBannerResource::collection(CasinoBanner::query()->showing()->limit(12)->get());
    }
}
