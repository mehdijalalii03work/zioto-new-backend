<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DigiExpressWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DigiExpressWebhookController extends Controller
{
    public function handle(Request $request, DigiExpressWebhookService $webhookService): JsonResponse
    {
        $expectedToken = (string) config('services.digiexpress.webhook_token');
        $providedToken = (string) ($request->header('X-DigiExpress-Token') ?: $request->header('Authorization'));

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json(['message' => 'احراز هویت وب‌هوک نامعتبر است'], 401);
        }

        $payload = $request->validate([
            'progress_type' => ['required', 'string', 'max:150'],
            'shipment_status' => ['nullable', 'string', 'max:100'],
            'parcel_status' => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'shipping_service_order_id' => ['nullable', 'string', 'max:100'],
            'shipping_service_shipment_id' => ['nullable', 'string', 'max:100'],
            'shipment_consignor_reference_id' => ['nullable', 'string', 'max:100'],
            'shipping_service_parcel_id' => ['nullable', 'string', 'max:100'],
            'parcel_shipping_code' => ['nullable', 'string', 'max:100'],
        ]);

        if (! filled($payload['shipment_status'] ?? null) && ! filled($payload['parcel_status'] ?? null)) {
            return response()->json(['message' => 'وضعیت ارسال یا پارسل الزامی است'], 422);
        }
        if (! filled($payload['shipment_consignor_reference_id'] ?? null)
            && ! filled($payload['shipping_service_shipment_id'] ?? null)
            && ! filled($payload['shipping_service_order_id'] ?? null)) {
            return response()->json(['message' => 'شناسه مرسوله برای تطبیق رخداد الزامی است'], 422);
        }

        $webhookService->receive($request->all());

        return response()->json(['received' => true]);
    }
}
