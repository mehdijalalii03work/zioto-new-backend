<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'text/plain',
        ])->post(self::BASE_URL.'/send/verify', [
            'mobile' => $mobile,
            'templateId' => (int) $this->templateId,
            'parameters' => [
                ['name' => 'OTP', 'value' => $code],
            ],
        ]);

        if ($response->failed()) {
            Log::error('sms.ir verify failed', [
                'mobile' => $mobile,
                'response' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    private function sendDirect(string $mobile, string $code): bool
    {
        $message = trans('messages.otp_sms', ['code' => $code]);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'text/plain',
        ])->post(self::BASE_URL.'/send/bulk', [
            'lineNumber' => $this->lineNumber,
            'messageText' => $message,
            'mobiles' => [$mobile],
        ]);

        if ($response->failed()) {
            Log::error('sms.ir bulk send failed', [
                'mobile' => $mobile,
                'response' => $response->body(),
            ]);

            return false;
        }

        return true;
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
