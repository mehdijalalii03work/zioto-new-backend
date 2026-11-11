<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DigiExpressService
{
    /** @return list<array<string, mixed>> */
    public function getCities(): array
    {
        $cities = $this->send('GET', '/api/cities')->json('data.cities');

        if (! is_array($cities)) {
            throw new RuntimeException('پاسخ فهرست شهرهای دیجی‌اکسپرس معتبر نیست.');
        }

        return $cities;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerShipment(array $payload): array
    {
        return $this->send('POST', '/api/v2/shipments', $payload)->json() ?? [];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function addParcel(string $shipmentId, array $payload): array
    {
        return $this->send('POST', '/api/v2/shipments/'.rawurlencode($shipmentId).'/parcels', $payload)->json() ?? [];
    }

    public function label(string $parcelIdOrCode): string
    {
        return $this->send(
            'GET',
            '/api/v2/parcels/'.rawurlencode($parcelIdOrCode).'/label',
            headers: ['Accept' => 'application/pdf'],
        )->body();
    }

    /** @return array<string, mixed> */
    public function cancelShipment(string $shipmentIdOrReference): array
    {
        return $this->send('PUT', '/api/v2/shipments/'.rawurlencode($shipmentIdOrReference).'/status/cancel')->json() ?? [];
    }

    /** @param list<string> $shipmentIdsOrReferences
     * @return array<string, mixed>
     */
    public function webhookFeed(array $shipmentIdsOrReferences): array
    {
        return $this->send('POST', '/api/v2/shipments/webhook-feed-request', [
            'shipment_ids_or_shipment_consignor_reference_ids' => $shipmentIdsOrReferences,
        ])->json() ?? [];
    }

    private function send(string $method, string $path, array $payload = [], array $headers = [], bool $retryAfterUnauthorized = true): Response
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');

        try {
            $response = $this->request($method, $url, $headers + ['Authorization' => $this->accessToken()], $payload);
        } catch (ConnectionException $exception) {
            throw $exception;
        }

        if ($response->unauthorized() && $retryAfterUnauthorized) {
            Cache::forget($this->tokenCacheKey());

            return $this->send($method, $path, $payload, $headers, retryAfterUnauthorized: false);
        }

        return $response->throw();
    }

    private function request(string $method, string $url, array $headers, array $payload): Response
    {
        $request = Http::connectTimeout(5)->timeout(25)->withHeaders($headers);

        return $method === 'GET'
            ? $request->get($url, $payload)
            : $request->{$method}($url, $payload);
    }

    private function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function (): string {
            $username = (string) config('services.digiexpress.username');
            $password = (string) config('services.digiexpress.password');

            if ($username === '' || $password === '') {
                throw new RuntimeException('اعتبارنامه دیجی‌اکسپرس در تنظیمات سرور کامل نیست.');
            }

            $response = Http::connectTimeout(5)
                ->timeout(25)
                ->post($this->baseUrl().'/api/customers/auth/login', [
                    'username' => $username,
                    'password' => $password,
                ])
                ->throw();

            $token = $response->json('data.access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('دیجی‌اکسپرس توکن دسترسی معتبری برنگرداند.');
            }

            return $token;
        });
    }

    private function tokenCacheKey(): string
    {
        return 'digiexpress:access_token:'.sha1($this->baseUrl());
    }

    private function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.digiexpress.base_url'));

        if ($baseUrl === '') {
            throw new RuntimeException('آدرس پایه دیجی‌اکسپرس در تنظیمات سرور تعیین نشده است.');
        }

        return rtrim($baseUrl, '/');
    }
}
