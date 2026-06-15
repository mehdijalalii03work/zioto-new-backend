<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\SmsIrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    private const OTP_TTL = 120;

    public function __construct(
        private readonly SmsIrService $sms
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

        if (! $user) {
            $user = User::create([
                'name' => 'کاربر '.$phone,
                'phone' => $phone,
                'email' => $phone.'@user.nopay',
                'password' => Hash::make(Str::random(32)),
                'phone_verified_at' => now(),
            ]);
        } else {
            $user->update(['phone_verified_at' => now()]);
        }

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

    public function logout(): JsonResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json([
            'message' => 'با موفقیت خارج شدید',
        ]);
    }
}
