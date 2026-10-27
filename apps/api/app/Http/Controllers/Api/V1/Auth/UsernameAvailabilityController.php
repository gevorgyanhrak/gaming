<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Platform\Auth\AccountRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsernameAvailabilityController extends Controller
{
    /**
     * Whether a username can be registered (valid format, not reserved, not taken).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['username' => ['required', 'string', 'max:50']]);

        $available = Validator::make(
            AccountRules::normalize($request->only('username')),
            ['username' => AccountRules::username()],
        )->passes();

        return response()->json(['data' => ['available' => $available]]);
    }
}
