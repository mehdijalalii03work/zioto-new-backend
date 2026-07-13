<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->with(['province', 'city'])
            ->get();

        return response()->json([
            'addresses' => AddressResource::collection($addresses),
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($request->validated());

        if ($request->boolean('is_default')) {
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->load(['province', 'city']);

        return response()->json([
            'message' => 'آدرس با موفقیت ایجاد شد',
            'address' => new AddressResource($address),
        ], 201);
    }

    public function show(Request $request, UserAddress $address): JsonResponse
    {
        if ($request->user()->id !== $address->user_id) {
            return response()->json([
                'message' => 'شما به این آدرس دسترسی ندارید',
            ], 403);
        }

        $address->load(['province', 'city']);

        return response()->json([
            'address' => new AddressResource($address),
        ]);
    }

    public function update(UpdateAddressRequest $request, UserAddress $address): JsonResponse
    {
        if ($request->user()->id !== $address->user_id) {
            return response()->json([
                'message' => 'شما به این آدرس دسترسی ندارید',
            ], 403);
        }

        $address->update($request->validated());

        if ($request->boolean('is_default')) {
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->load(['province', 'city']);

        return response()->json([
            'message' => 'آدرس با موفقیت بروزرسانی شد',
            'address' => new AddressResource($address),
        ]);
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        if ($request->user()->id !== $address->user_id) {
            return response()->json([
                'message' => 'شما به این آدرس دسترسی ندارید',
            ], 403);
        }

        $address->delete();

        return response()->json([
            'message' => 'آدرس با موفقیت حذف شد',
        ]);
    }

    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        if ($request->user()->id !== $address->user_id) {
            return response()->json([
                'message' => 'شما به این آدرس دسترسی ندارید',
            ], 403);
        }

        $request->user()->addresses()
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'آدرس پیش‌فرض با موفقیت تنظیم شد',
            'address' => new AddressResource($address),
        ]);
    }
}
