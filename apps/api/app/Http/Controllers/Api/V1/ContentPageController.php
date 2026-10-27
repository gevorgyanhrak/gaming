<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentPageResource;
use App\Models\ContentPage;

class ContentPageController extends Controller
{
    /**
     * A published content page (e.g. privacy policy, terms of service).
     */
    public function show(string $slug): ContentPageResource
    {
        return new ContentPageResource(
            ContentPage::query()->published()->where('slug', $slug)->firstOrFail(),
        );
    }
}
