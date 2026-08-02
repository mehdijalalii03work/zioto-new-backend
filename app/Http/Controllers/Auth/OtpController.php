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
use Illuminate\Support\Facades\Log;

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
                'error_code' => 'OTP_STILL_VALID',
            ], 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put($key, $code, self::OTP_TTL);

        $sent = $this->sms->sendVerificationCode($phone, $code);

        if (! $sent) {
            Cache::forget($key);

            return response()->json([
                'message' => 'ارسال پیامک با مشکل مواجه شد، لطفا دقایقی دیگر تلاش کنید',
                'error_code' => 'SMS_FAILED',
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
                'error_code' => 'OTP_INVALID',
            ], 422);
        }

        Cache::forget($key);

        $platform = \App\Support\Platform::fromRequest($request);
        $user = User::withoutTenantScope()->where('phone', $phone)->where('platform', $platform)->first();

        if ($user) {
            $apiToken = Str::random(64);
            $user->update([
                'phone_verified_at' => now(),
                'api_token' => $apiToken,
                'api_token_hash' => hash('sha256', $apiToken),
                'token_created_at' => now(),
            ]);
            Auth::guard('api')->setUser($user);

            return response()->json([
                'message' => 'با موفقیت وارد شدید',
                'token' => $apiToken,
                'user' => new UserResource($user),
            ]);
        }

        $token = Str::random(64);
        $platform = \App\Support\Platform::fromRequest($request);
        Cache::put("shahkar_register_token:{$token}", ['phone' => $phone, 'platform' => $platform], self::SHAHKAR_TOKEN_TTL);

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

        $registerData = Cache::pull("shahkar_register_token:{$token}");
        $phone = is_array($registerData) ? ($registerData['phone'] ?? null) : $registerData;

        if (! $phone) {
            return response()->json([
                'message' => 'توکن نامعتبر یا منقضی شده است، لطفاً مجدداً وارد شوید',
                'error_code' => 'TOKEN_EXPIRED',
            ], 422);
        }

        // The platform is bound to the registration token so a user can
        // register on main and nopay independently with the same phone.
        $platform = is_array($registerData) ? ($registerData['platform'] ?? 'main') : \App\Support\Platform::fromRequest($request);
        $existingUser = User::withoutTenantScope()
            ->where('national_code', $nationalCode)
            ->where('platform', $platform)
            ->first();
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
            'platform' => $platform,
            'api_token' => $apiToken,
            'api_token_hash' => hash('sha256', $apiToken),
            'token_created_at' => now(),
        ]);

        Auth::guard('api')->setUser($user);

        return response()->json([
            'message' => 'احراز هویت با موفقیت انجام شد',
            'token' => $apiToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        // Token-based (Bearer) auth: logging out means revoking the token.
        // There is no session/cookie to invalidate in this stateless API.
        $user = Auth::user();

        if ($user) {
            $user->update([
                'api_token' => null,
                'api_token_hash' => null,
                'token_created_at' => null,
            ]);
        }

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
