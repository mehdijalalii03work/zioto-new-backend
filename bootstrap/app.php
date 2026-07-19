<?php

use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Http\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateApiToken::class,
        ]);
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PREFIX |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
        $middleware->api(prepend: [
            HandleCors::class,
        ]);
        $middleware->web(prepend: [
            HandleCors::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'error_code' => 'VALIDATION_FAILED',
                ], 422);
            }
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'رکورد مورد نظر یافت نشد',
                    'error_code' => 'MODEL_NOT_FOUND',
                ], 404);
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'آدرس یافت نشد',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'روش درخواست مجاز نیست',
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ], 405);
            }
        });

        $exceptions->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است',
                    'error_code' => 'RATE_LIMITED',
                ], 429);
            }
        });

        $exceptions->renderable(function (BadRequestHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'درخواست نامعتبر است',
                    'error_code' => 'BAD_REQUEST',
                ], 400);
            }
        });

        $exceptions->renderable(function (Exception $e, Request $request) {
            if ($request->is('api/*') && ! $e instanceof ValidationException && ! $e instanceof ModelNotFoundException && ! $e instanceof NotFoundHttpException && ! $e instanceof MethodNotAllowedHttpException && ! $e instanceof ThrottleRequestsException && ! $e instanceof BadRequestHttpException) {
                Log::error('Unhandled API exception: '.$e->getMessage(), [
                    'exception' => $e,
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'خطای داخلی سرور',
                    'error_code' => 'SERVER_ERROR',
                ], 500);
            }
        });
    })->create();
