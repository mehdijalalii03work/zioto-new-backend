<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ], [
            'name.required' => 'نام الزامی است',
            'name.max' => 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'phone.required' => 'شماره تماس الزامی است',
            'phone.max' => 'شماره تماس نباید بیشتر از ۲۰ کاراکتر باشد',
            'subject.max' => 'موضوع نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'message.required' => 'متن پیام الزامی است',
            'message.max' => 'متن پیام نباید بیشتر از ۲۰۰۰ کاراکتر باشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        ContactMessage::create($validator->validated());

        return response()->json([
            'message' => 'پیام شما با موفقیت ارسال شد',
        ], 201);
    }
}
