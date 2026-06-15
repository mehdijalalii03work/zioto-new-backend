<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShahkarService
{
    private string $apiKey;

    private string $secretKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.shahkar.api_key');
        $this->secretKey = config('services.shahkar.secret_key');
        $this->baseUrl = config('services.shahkar.base_url', 'https://napi.jibit.ir/ide');
    }

    public function verify(string $nationalCode, string $mobile): array
    {
        if (empty($this->apiKey) || empty($this->secretKey)) {
            Log::error('Shahkar API credentials not configured');

            return ['success' => false, 'reason' => 'config_missing', 'message' => 'تنظیمات احراز هویت یافت نشد'];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'reason' => 'token_error', 'message' => 'خطا در دریافت توکن احراز هویت'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->get("{$this->baseUrl}/services/matching", [
                'nationalCode' => $nationalCode,
                'mobileNumber' => $mobile,
            ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'matched' => $body['matched'] ?? false,
                ];
            }

            $body = $response->json();
            $reason = $body['reason'] ?? 'unknown';
            $message = $this->getErrorMessage($reason);

            Log::warning('Shahkar verification failed', [
                'national_code' => substr($nationalCode, 0, 3).'***'.substr($nationalCode, -2),
                'reason' => $reason,
            ]);

            return ['success' => false, 'reason' => $reason, 'message' => $message];
        } catch (\Exception $e) {
            Log::error('Shahkar API error: '.$e->getMessage());

            return ['success' => false, 'reason' => 'connection_error', 'message' => 'خطا در ارتباط با سرویس احراز هویت'];
        }
    }

    private function getAccessToken(): ?string
    {
        $cached = Cache::get('shahkar_access_token');
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)->post("{$this->baseUrl}/tokens/generate", [
                'apiKey' => $this->apiKey,
                'secretKey' => $this->secretKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['accessToken'] ?? null;

                if ($token) {
                    Cache::put('shahkar_access_token', $token, 23 * 3600);
                }

                return $token;
            }

            Log::error('Shahkar token generation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Shahkar token error: '.$e->getMessage());

            return null;
        }
    }

    private function getErrorMessage(string $reason): string
    {
        return match ($reason) {
            'not_matched' => 'کد ملی وارد شده متعلق به این شماره موبایل نیست',
            'invalid_national_code' => 'کد ملی وارد شده در سامانه ثبت احوال یافت نشد',
            'invalid_mobile' => 'شماره موبایل در سامانه شاهکار ثبت نشده است',
            'service_unavailable' => 'سرویس احراز هویت موقتاً در دسترس نیست',
            'rate_limit' => 'تعداد درخواست‌ها بیش از حد مجاز است، لطفاً ۵ دقیقه دیگر تلاش کنید',
            default => 'خطا در احراز هویت، لطفاً دوباره تلاش کنید',
        };
    }
}
