<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChipPackageResource;
use App\Models\ChipPackage;
use App\Platform\Payments\Payments;
use Illuminate\Http\JsonResponse;

class ChipPackageController extends Controller
{
    /**
     * The chip packages on sale, and whether buying works now (`purchasable`: false until a payment provider is
     * connected and switched on — then the UI shows the packages without a way to pay).
     */
    public function index(Payments $payments): JsonResponse
    {
        return response()->json([
            'data' => ChipPackageResource::collection(ChipPackage::query()->onSale()->get()),
            /** @var bool */
            'purchasable' => $payments->active() !== null,
        ]);
    }
}
