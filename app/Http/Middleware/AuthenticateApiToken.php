<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
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
        $user = User::where('api_token_hash', $tokenHash)->first();

        if (! $user) {
            return response()->json(['message' => 'توکن نامعتبر است', 'error_code' => 'TOKEN_INVALID'], 401);
        }

        if ($user->token_created_at && $user->token_created_at->diffInHours(now()) > self::TOKEN_MAX_AGE_HOURS) {
            return response()->json(['message' => 'توکن منقضی شده است، لطفاً مجدداً وارد شوید', 'error_code' => 'TOKEN_EXPIRED'], 401);
        }

        auth()->login($user);

        return $next($request);
    }
}
