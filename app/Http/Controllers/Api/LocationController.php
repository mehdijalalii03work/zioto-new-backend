<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function provinces(): JsonResponse
    {
        $provinces = Province::ordered()->get();

        return response()->json([
            'provinces' => $provinces,
        ]);
    }

    public function cities(Province $province): JsonResponse
    {
        $cities = $province->cities()->orderBy('name')->get();

        return response()->json([
            'province' => $province,
            'cities' => $cities,
        ]);
    }
}
