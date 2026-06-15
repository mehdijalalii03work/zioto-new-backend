<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\ShahkarVerifyRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\ShahkarService;
use App\Services\SmsIrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    private const OTP_TTL = 120;

    private const SHAHKAR_TOKEN_TTL = 600;

    public function __construct(
        private readonly SmsIrService $sms,
        private readonly ShahkarService $shahkar
    ) {}

    public function send(SendOtpRequest $request): JsonResponse
    {
        $phone = $request->input('phone');

        $existing = Redis::get("otp:{$phone}");
        if ($existing) {
            $ttl = Redis::ttl("otp:{$phone}");
            if ($ttl > 90) {
                return response()->json([
                    'message' => 'کد تایید قبلی هنوز معتبر است، لطفاً پس از ۲ دقیقه دوباره تلاش کنید',
                ], 429);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Redis::setex("otp:{$phone}", self::OTP_TTL, $code);

        $sent = $this->sms->sendVerificationCode($phone, $code);

        if (! $sent) {
            Redis::del("otp:{$phone}");

            return response()->json([
                'message' => 'ارسال پیامک با مشکل مواجه شد، لطفا دقایقی دیگر تلاش کنید',
            ], 500);
        }

        return response()->json([
            'message' => 'کد تایید با موفقیت ارسال شد',
        ]);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->input('phone');
        $code = $request->input('code');

        $stored = Redis::get("otp:{$phone}");

        if (! $stored || $stored !== $code) {
            return response()->json([
                'message' => 'کد تایید نامعتبر یا منقضی شده است',
            ], 422);
        }

        Redis::del("otp:{$phone}");

        $user = User::where('phone', $phone)->first();

        if ($user) {
            $user->update(['phone_verified_at' => now()]);
            Auth::login($user);

            return response()->json([
                'message' => 'با موفقیت وارد شدید',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                ],
            ]);
        }

        $token = Str::random(64);
        Cache::put("shahkar_register_token:{$token}", $phone, self::SHAHKAR_TOKEN_TTL);

        return response()->json([
            'message' => 'کد تایید شد',
            'requires_registration' => true,
            'token' => $token,
        ]);
    }

    public function shahkarVerify(ShahkarVerifyRequest $request): JsonResponse
    {
        $token = $request->input('token');
        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');
        $nationalCode = $request->input('national_code');

        $phone = Cache::pull("shahkar_register_token:{$token}");

        if (! $phone) {
            return response()->json([
                'message' => 'توکن نامعتبر یا منقضی شده است، لطفاً مجدداً وارد شوید',
            ], 422);
        }

        $existingUser = User::where('national_code', $nationalCode)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'این کد ملی قبلاً ثبت شده است',
            ], 422);
        }

        $normalizedMobile = $this->normalizeMobile($phone);
        $result = $this->shahkar->verify($nationalCode, $normalizedMobile);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'خطا در احراز هویت',
            ], 422);
        }

        if (! ($result['matched'] ?? false)) {
            return response()->json([
                'message' => 'کد ملی و شماره موبایل مطابقت ندارند',
            ], 422);
        }

        $user = User::create([
            'name' => $firstName.' '.$lastName,
            'phone' => $phone,
            'email' => $phone.'@user.nopay',
            'password' => Hash::make(Str::random(32)),
            'phone_verified_at' => now(),
            'national_code' => $nationalCode,
            'shahkar_verified' => true,
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'احراز هویت با موفقیت انجام شد',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json([
            'message' => 'با موفقیت خارج شدید',
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
