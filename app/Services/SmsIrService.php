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

    public function sendWelcomeMessage(string $mobile, string $name): bool
    {
        $endpoint = '/send/verify';
        $requestBody = [
            'mobile' => $mobile,
            'templateId' => 331254,
            'parameters' => [
                ['name' => 'NAME', 'value' => $name],
            ],
        ];
        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/plain',
            ])->post(self::BASE_URL.$endpoint, $requestBody);

            $success = $this->isSmsIrResponseSuccessful($response);

            try {
                $this->logApiCallSafely($endpoint, $mobile, $requestBody, $response, (int) ((microtime(true) - $start) * 1000), $success);
            } catch (Throwable) {
                // A logging failure must not cause a retry of an accepted SMS.
            }

            if (! $success) {
                try {
                    Log::error('sms.ir welcome message failed', [
                        'mobile' => $mobile,
                        'response' => $response->body(),
                    ]);
                } catch (Throwable) {
                    // The SMS response remains authoritative if logging is unavailable.
                }

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            try {
                $this->logConnectionErrorSafely($endpoint, $mobile, $requestBody, $exception, (int) ((microtime(true) - $start) * 1000));
            } catch (Throwable) {
                // Keep the queue retry decision independent of logging availability.
            }

            return false;
        }
    }

    public function sendPickupOrderConfirmation(string $mobile, string $name, string $code, int $price, string $date): bool
    {
        return $this->sendOrderTemplate($mobile, 196926, [
            ['name' => 'Name', 'value' => $name],
            ['name' => 'CODE', 'value' => $code],
            ['name' => 'PRICE', 'value' => (string) $price],
            ['name' => 'DATE', 'value' => $date],
        ]);
    }

    public function sendShippingOrderConfirmation(string $mobile, string $name, string $code, int $amount, string $date): bool
    {
        return $this->sendOrderTemplate($mobile, 832720, [
            ['name' => 'Name', 'value' => $name],
            ['name' => 'CODE', 'value' => $code],
            ['name' => 'AMOUNT', 'value' => (string) $amount],
            ['name' => 'TIME', 'value' => $date],
        ]);
    }

    public function sendOrderReceivedHealthMessage(string $mobile, string $name): bool
    {
        return $this->sendOrderTemplate($mobile, 990301, [
            ['name' => 'NAME', 'value' => 'سرمایه گذار'],
        ]);
    }

    /**
     * @param  array<int, array{name: string, value: string}>  $parameters
     */
    private function sendOrderTemplate(string $mobile, int $templateId, array $parameters): bool
    {
        $endpoint = '/send/verify';
        $requestBody = ['mobile' => $mobile, 'templateId' => $templateId, 'parameters' => $parameters];
        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/plain',
            ])->post(self::BASE_URL.$endpoint, $requestBody);
        } catch (Throwable $exception) {
            try {
                $this->logConnectionError($endpoint, $mobile, $requestBody, $exception, (int) ((microtime(true) - $start) * 1000));
            } catch (Throwable) {
                // Logging must not alter delivery behavior.
            }

            return false;
        }

        $body = $response->json();
        $success = $response->successful()
            && (! is_array($body) || ! array_key_exists('status', $body) || (int) $body['status'] === 1);

        try {
            $this->logApiCall($endpoint, $mobile, $requestBody, $response, (int) ((microtime(true) - $start) * 1000), $success);
        } catch (Throwable) {
            // A log failure must not cause a duplicate SMS retry.
        }

        if (! $success) {
            try {
                Log::error('sms.ir order notification failed', ['mobile' => $mobile, 'response' => $response->body()]);
            } catch (Throwable) {
                // The provider response remains authoritative.
            }
        }

        return $success;
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
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
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

            $success = $this->isSmsIrResponseSuccessful($response);
            $this->logApiCallSafely($endpoint, $mobile, $requestBody, $response, (int) ((microtime(true) - $start) * 1000), $success);

            if (! $success) {
                try {
                    Log::error('sms.ir verify failed', [
                        'mobile' => $mobile,
                        'response' => $response->body(),
                    ]);
                } catch (Throwable) {
                    // Logging must not change the provider result.
                }

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
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/plain',
                ])->post(self::BASE_URL.$endpoint, [
                    'lineNumber' => $this->lineNumber,
                    'messageText' => $message,
                    'mobiles' => [$mobile],
                ]);

            $success = $this->isSmsIrResponseSuccessful($response);
            $this->logApiCallSafely($endpoint, $mobile, $requestBody, $response, (int) ((microtime(true) - $start) * 1000), $success);

            if (! $success) {
                try {
                    Log::error('sms.ir bulk send failed', [
                        'mobile' => $mobile,
                        'response' => $response->body(),
                    ]);
                } catch (Throwable) {
                    // Logging must not change the provider result.
                }

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
    private function logApiCall(string $endpoint, string $mobile, array $requestBody, Response $response, int $durationMs, ?bool $successOverride = null): void
    {
        $responseBody = $response->json();
        $success = $successOverride ?? ! $response->failed();
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

    private function isSmsIrResponseSuccessful(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $responseBody = $response->json();

        return ! is_array($responseBody)
            || ! array_key_exists('status', $responseBody)
            || (int) $responseBody['status'] === 1;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function logApiCallSafely(string $endpoint, string $mobile, array $requestBody, Response $response, int $durationMs, ?bool $successOverride = null): void
    {
        try {
            $this->logApiCall($endpoint, $mobile, $requestBody, $response, $durationMs, $successOverride);
        } catch (Throwable $exception) {
            try {
                Log::warning('Failed to store sms.ir API log', [
                    'mobile' => $mobile,
                    'error' => $exception->getMessage(),
                ]);
            } catch (Throwable) {
                // Logging failures must not change SMS delivery behavior.
            }
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
            try {
                Log::warning('Failed to store sms.ir connection error log', [
                    'mobile' => $mobile,
                    'error' => $logException->getMessage(),
                ]);
            } catch (Throwable) {
                // Logging failures must not change SMS delivery behavior.
            }
        }

        try {
            Log::error('sms.ir connection error: '.$exception->getMessage());
        } catch (Throwable) {
            // Logging failures must not change SMS delivery behavior.
        }
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
