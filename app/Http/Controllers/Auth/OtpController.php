<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\ShahkarVerifyRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ShahkarService;
use App\Services\SmsIrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    private const OTP_TTL = 180;

    private const SHAHKAR_TOKEN_TTL = 1800;

    public function __construct(
        private readonly SmsIrService $sms,
        private readonly ShahkarService $shahkar
    ) {}

    public function send(SendOtpRequest $request): JsonResponse
    {
        $phone = $request->input('phone');
        $key = "otp:{$phone}";

        if (Cache::has($key)) {
            return response()->json([
                'message' => 'کد تایید قبلی هنوز معتبر است، لطفاً پس از ۲ دقیقه دوباره تلاش کنید',
            ], 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put($key, $code, self::OTP_TTL);

        $sent = $this->sms->sendVerificationCode($phone, $code);

        if (! $sent) {
            Cache::forget($key);

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
        $key = "otp:{$phone}";

        $stored = Cache::get($key);

        if (! $stored || $stored !== $code) {
            return response()->json([
                'message' => 'کد تایید نامعتبر یا منقضی شده است',
            ], 422);
        }

        Cache::forget($key);

        $user = User::where('phone', $phone)->first();

        if ($user) {
            $apiToken = Str::random(64);
            $user->update([
                'phone_verified_at' => now(),
                'api_token' => $apiToken,
                'api_token_hash' => hash('sha256', $apiToken),
                'token_created_at' => now(),
            ]);
            Auth::login($user);

            return response()->json([
                'message' => 'با موفقیت وارد شدید',
                'token' => $apiToken,
                'user' => new UserResource($user),
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
        $birthDate = $request->input('birth_date');

        $phone = Cache::pull("shahkar_register_token:{$token}");

        if (! $phone) {
            return response()->json([
                'message' => 'توکن نامعتبر یا منقضی شده است، لطفاً مجدداً وارد شوید',
                'error_code' => 'TOKEN_EXPIRED',
            ], 422);
        }

        $existingUser = User::where('national_code', $nationalCode)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'این کد ملی قبلاً ثبت شده است',
                'error_code' => 'NATIONAL_CODE_DUPLICATE',
            ], 422);
        }

        $normalizedMobile = $this->normalizeMobile($phone);
        $result = $this->shahkar->verify($nationalCode, $normalizedMobile);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'خطا در احراز هویت',
                'error_code' => 'SHAHKAR_FAILED',
            ], 422);
        }

        if (! ($result['matched'] ?? false)) {
            return response()->json([
                'message' => 'کد ملی و شماره موبایل مطابقت ندارند',
                'error_code' => 'NATIONAL_CODE_MISMATCH',
            ], 422);
        }

        $apiToken = Str::random(64);

        $user = User::create([
            'name' => $firstName.' '.$lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => null,
            'password' => Hash::make(Str::random(32)),
            'phone_verified_at' => now(),
            'national_code' => $nationalCode,
            'shahkar_verified' => true,
            'birth_date' => $birthDate,
            'api_token' => $apiToken,
            'api_token_hash' => hash('sha256', $apiToken),
            'token_created_at' => now(),
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'احراز هویت با موفقیت انجام شد',
            'token' => $apiToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            $user->update([
                'api_token' => null,
                'api_token_hash' => null,
                'token_created_at' => null,
            ]);
        }

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
