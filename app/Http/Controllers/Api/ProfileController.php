<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'min:2', 'max:50'],
            'last_name' => ['sometimes', 'string', 'min:2', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
        ]);

        $data = [];

        if ($request->has('first_name')) {
            $data['first_name'] = $validated['first_name'];
        }

        if ($request->has('last_name')) {
            $data['last_name'] = $validated['last_name'];
        }

        if ($request->has('first_name') && $request->has('last_name')) {
            $data['name'] = $validated['first_name'].' '.$validated['last_name'];
        } elseif ($request->has('first_name') && $user->last_name) {
            $data['name'] = $validated['first_name'].' '.$user->last_name;
        } elseif ($request->has('last_name') && $user->first_name) {
            $data['name'] = $user->first_name.' '.$validated['last_name'];
        }

        if ($request->has('email')) {
            $data['email'] = $validated['email'] ?: null;
        }

        if ($request->has('birth_date')) {
            $data['birth_date'] = $validated['birth_date'];
        }

        $user->update($data);

        return response()->json([
            'message' => 'اطلاعات با موفقیت بروزرسانی شد',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'national_id' => $user->national_code,
                'birth_date' => $user->birth_date,
            ],
        ]);
    }
}
