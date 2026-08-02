<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Platform;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    private const TOKEN_MAX_AGE_HOURS = 72;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'توکن احراز هویت ارسال نشده است', 'error_code' => 'TOKEN_MISSING'], 401);
        }

        $tokenHash = hash('sha256', $token);

        // Tokens are scoped per platform: a main-site token must not
        // authenticate on the nopay landing and vice versa.
        $platform = Platform::fromRequest($request);
        $user = User::withoutTenantScope()
            ->where('api_token_hash', $tokenHash)
            ->where('platform', $platform)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'توکن نامعتبر است', 'error_code' => 'TOKEN_INVALID'], 401);
        }

        if ($user->token_created_at && $user->token_created_at->diffInHours(now()) > self::TOKEN_MAX_AGE_HOURS) {
            return response()->json(['message' => 'توکن منقضی شده است، لطفاً مجدداً وارد شوید', 'error_code' => 'TOKEN_EXPIRED'], 401);
        }

        // Stateless API guard — no session involved.
        Auth::guard('api')->setUser($user);

        return $next($request);
    }
}
