<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShahkarService;
use App\Services\SmsIrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    private const OTP_TTL = 180;

    private const SHAHKAR_TOKEN_TTL = 600;

    public function __construct(
        private readonly SmsIrService $sms,
        private readonly ShahkarService $shahkar
    ) {}
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

    public function changePhoneSendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ]);

        $newPhone = $validated['phone'];
        $user = Auth::user();

        if ($newPhone === $user->phone) {
            return response()->json([
                'message' => 'شماره جدید با شماره فعلی یکسان است',
            ], 422);
        }

        $existingUser = \App\Models\User::where('phone', $newPhone)->where('id', '!=', $user->id)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'این شماره تلفن قبلاً ثبت شده است',
            ], 422);
        }

        $key = "change_phone_otp:{$newPhone}";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'کد تایید قبلی هنوز معتبر است، لطفاً پس از ۲ دقیقه دوباره تلاش کنید',
            ], 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        Cache::put($key, $code, self::OTP_TTL);

        $sent = $this->sms->sendVerificationCode($newPhone, $code);

        if (! $sent) {
            Cache::forget($key);

            return response()->json([
                'message' => 'ارسال پیامک با مشکل مواجه شد، لطفا دقایقی دیگر تلاش کنید',
            ], 500);
        }

        $token = \Illuminate\Support\Str::random(64);
        Cache::put("change_phone_token:{$token}", $newPhone, self::SHAHKAR_TOKEN_TTL);

        return response()->json([
            'message' => 'کد تایید با موفقیت ارسال شد',
            'token' => $token,
        ]);
    }

    public function changePhoneVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:5'],
        ]);

        $token = $validated['token'];
        $code = $validated['code'];

        $newPhone = Cache::pull("change_phone_token:{$token}");
        if (! $newPhone) {
            return response()->json([
                'message' => 'توکن نامعتبر یا منقضی شده است، لطفاً مجدداً تلاش کنید',
            ], 422);
        }

        $otpKey = "change_phone_otp:{$newPhone}";
        $storedCode = Cache::get($otpKey);

        if (! $storedCode || $storedCode !== $code) {
            return response()->json([
                'message' => 'کد تایید نامعتبر یا منقضی شده است',
            ], 422);
        }

        Cache::forget($otpKey);

        $user = Auth::user();

        $normalizedMobile = $this->normalizeMobile($newPhone);
        $result = $this->shahkar->verify($user->national_code, $normalizedMobile);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'خطا در احراز هویت',
            ], 422);
        }

        if (! ($result['matched'] ?? false)) {
            return response()->json([
                'message' => 'شماره موبایل جدید با کد ملی شما مطابقت ندارد',
            ], 422);
        }

        $user->update([
            'phone' => $newPhone,
            'phone_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'شماره تلفن با موفقیت تغییر کرد',
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
            ],
        ]);
    }

    private function normalizeMobile(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 13 && str_starts_with($phone, '98')) {
            $phone = '0'.substr($phone, 2);
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '+98')) {
            $phone = '0'.substr($phone, 3);
        }

        return $phone;
    }
}
