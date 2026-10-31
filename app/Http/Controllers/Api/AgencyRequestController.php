<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AgencyRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'mobile' => 'required|string|max:20',
            'gender' => 'required|in:male,female',
            'marital_status' => 'required|in:single,married',
            'agency_province_id' => 'required|integer|exists:provinces,id',
            'agency_city_id' => 'required|integer|exists:cities,id',
            'residence_province_id' => 'required|integer|exists:provinces,id',
            'residence_city_id' => 'required|integer|exists:cities,id',
            'neighborhood' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
        ], [
            'first_name.required' => 'نام الزامی است',
            'last_name.required' => 'نام خانوادگی الزامی است',
            'birth_date.required' => 'تاریخ تولد الزامی است',
            'email.email' => 'پست الکترونیک معتبر نیست',
            'phone.required' => 'تلفن الزامی است',
            'mobile.required' => 'تلفن همراه الزامی است',
            'father_name.required' => 'نام پدر الزامی است',
            'father_job.required' => 'شغل پدر الزامی است',
            'gender.required' => 'جنسیت الزامی است',
            'gender.in' => 'جنسیت نامعتبر است',
            'marital_status.required' => 'وضعیت تاهل الزامی است',
            'marital_status.in' => 'وضعیت تاهل نامعتبر است',
            'agency_province_id.required' => 'استان اخذ نمایندگی الزامی است',
            'agency_province_id.exists' => 'استان اخذ نمایندگی نامعتبر است',
            'agency_city_id.required' => 'شهر اخذ نمایندگی الزامی است',
            'agency_city_id.exists' => 'شهر اخذ نمایندگی نامعتبر است',
            'residence_province_id.required' => 'استان محل سکونت الزامی است',
            'residence_province_id.exists' => 'استان محل سکونت نامعتبر است',
            'residence_city_id.required' => 'شهر محل سکونت الزامی است',
            'residence_city_id.exists' => 'شهر محل سکونت نامعتبر است',
            'neighborhood.required' => 'محله الزامی است',
            'address.required' => 'آدرس الزامی است',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }

        AgencyRequest::create($validator->validated());

        return response()->json([
            'message' => 'درخواست نمایندگی شما با موفقیت ثبت شد',
        ], 201);
    }
}
