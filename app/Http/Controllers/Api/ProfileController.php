<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'national_id' => $user->national_code,
                'birth_date' => $user->birth_date,
                'shahkar_verified' => $user->shahkar_verified,
                'phone_verified_at' => $user->phone_verified_at,
                'created_at' => $user->created_at,
            ],
        ]);
    }
}
