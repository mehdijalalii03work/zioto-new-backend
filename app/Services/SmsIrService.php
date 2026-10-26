<?php

namespace App\Services;

use App\Models\SmsIrApiLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsIrService
{
    private string $apiKey;

    private ?string $lineNumber;

    private ?int $templateId;

    private const BASE_URL = 'https://api.sms.ir/v1';

    public function __construct()
    {
        $this->apiKey = config('sms.smsir.api_key');
        $this->lineNumber = config('sms.smsir.line_number');
        $this->templateId = config('sms.smsir.template_id') ? (int) config('sms.smsir.template_id') : null;
    }

    public function sendVerificationCode(string $mobile, string $code): bool
    {
        if ($this->templateId) {
            return $this->sendWithTemplate($mobile, $code);
        }

        return $this->sendDirect($mobile, $code);
    }

    private function sendWithTemplate(string $mobile, string $code): bool
    {
        $endpoint = '/send/verify';
        $requestBody = [
            'mobile' => $mobile,
            'templateId' => (int) $this->templateId,
            'parameters' => [
                ['name' => 'OTP', 'value' => '*****'],
            ],
        ];
        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/plain',
            ])->post(self::BASE_URL.$endpoint, [
                'mobile' => $mobile,
                'templateId' => (int) $this->templateId,
                'parameters' => [
                    ['name' => 'OTP', 'value' => $code],
                ],
            ]);

            $this->logApiCallSafely($endpoint, $mobile, $requestBody, $response, (int) ((microtime(true) - $start) * 1000));

            if ($response->failed()) {
                Log::error('sms.ir verify failed', [
                    'mobile' => $mobile,
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            $this->logConnectionErrorSafely($endpoint, $mobile, $requestBody, $exception, (int) ((microtime(true) - $start) * 1000));

            throw $exception;
        }
    }

    private function sendDirect(string $mobile, string $code): bool
    {
        $message = trans('messages.otp_sms', ['code' => $code]);
        $loggedMessage = str_replace($code, '*****', $message);

        $endpoint = '/send/bulk';
        $requestBody = [
            'lineNumber' => $this->lineNumber,
            'messageText' => $loggedMessage,
            'mobiles' => [$mobile],
        ];
        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/plain',
            ])->post(self::BASE_URL.$endpoint, [
                'lineNumber' => $this->lineNumber,
                'messageText' => $message,
                'mobiles' => [$mobile],
            ]);

            $this->logApiCallSafely($endpoint, $mobile, $requestBody, $response, (int) ((microtime(true) - $start) * 1000));

            if ($response->failed()) {
                Log::error('sms.ir bulk send failed', [
                    'mobile' => $mobile,
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            $this->logConnectionErrorSafely($endpoint, $mobile, $requestBody, $exception, (int) ((microtime(true) - $start) * 1000));

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function logApiCall(string $endpoint, string $mobile, array $requestBody, Response $response, int $durationMs): void
    {
        $responseBody = $response->json();
        $success = ! $response->failed();
        $errorCode = $responseBody['code'] ?? $responseBody['status'] ?? null;
        $errorMessage = $success ? null : ($responseBody['message'] ?? $response->body());

        $this->storeApiLog([
            'endpoint' => $endpoint,
            'method' => 'POST',
            'request_body' => $requestBody,
            'response_status' => $response->status(),
            'response_body' => $responseBody ?? ['raw' => $response->body()],
            'duration_ms' => $durationMs,
            'success' => $success,
            'error_message' => $errorMessage === null
                ? null
                : (is_string($errorMessage) ? $errorMessage : json_encode($errorMessage, JSON_UNESCAPED_UNICODE)),
            'mobile' => $mobile,
            'error_code' => $errorCode,
        ]);

    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function logApiCallSafely(string $endpoint, string $mobile, array $requestBody, Response $response, int $durationMs): void
    {
        try {
            $this->logApiCall($endpoint, $mobile, $requestBody, $response, $durationMs);
        } catch (Throwable $exception) {
            Log::warning('Failed to store sms.ir API log', [
                'mobile' => $mobile,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function logConnectionError(string $endpoint, string $mobile, array $requestBody, Throwable $exception, int $durationMs): void
    {
        $this->storeApiLog([
            'endpoint' => $endpoint,
            'method' => 'POST',
            'request_body' => $requestBody,
            'response_status' => null,
            'response_body' => null,
            'duration_ms' => $durationMs,
            'success' => false,
            'error_message' => $exception->getMessage(),
            'mobile' => $mobile,
        ]);

    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function logConnectionErrorSafely(string $endpoint, string $mobile, array $requestBody, Throwable $exception, int $durationMs): void
    {
        try {
            $this->logConnectionError($endpoint, $mobile, $requestBody, $exception, $durationMs);
        } catch (Throwable $logException) {
            Log::warning('Failed to store sms.ir connection error log', [
                'mobile' => $mobile,
                'error' => $logException->getMessage(),
            ]);
        }

        Log::error('sms.ir connection error: '.$exception->getMessage());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function storeApiLog(array $attributes): void
    {
        SmsIrApiLog::create($attributes);
    }

    public function getCredit(): ?float
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->get(self::BASE_URL.'/credit');

        if ($response->successful()) {
            return $response->json('data');
        }

        return null;
    }
}
