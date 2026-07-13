<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HesabfaService;
use App\Services\StockSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HesabfaWebhookController extends Controller
{
    public function __construct(
        private HesabfaService $hesabfa,
        private StockSyncService $stockSync,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $this->logWebhook($request);

        $secret = config('hesabfa.webhook_secret');
        if (empty($secret)) {
            Log::warning('Hesabfa webhook: webhook_secret not configured, rejecting request');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        $providedSecret = $request->header('X-Webhook-Secret')
            ?? $request->input('secret')
            ?? $request->query('secret');

        if ($providedSecret !== $secret) {
            Log::warning('Hesabfa webhook: invalid secret');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $eventType = $data['EventType'] ?? $data['eventType'] ?? null;

        if (! $eventType) {
            return response()->json(['message' => 'No event type']);
        }

        Log::info('Hesabfa webhook received', ['event_type' => $eventType]);

        return match ($eventType) {
            'ItemQuantityChanged' => $this->handleQuantityChange($data),
            'ItemPriceChanged' => $this->handlePriceChange($data),
            default => response()->json(['message' => "Unhandled event: {$eventType}"]),
        };
    }

    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'وب‌هوک حسابفا فعال است',
            'configured' => $this->hesabfa->isConfigured(),
        ]);
    }

    private function handleQuantityChange(array $data): JsonResponse
    {
        $itemCode = $data['ItemCode'] ?? $data['itemCode'] ?? null;
        $quantity = $data['Quantity'] ?? $data['quantity'] ?? null;

        if (! $itemCode || $quantity === null) {
            return response()->json(['error' => 'Missing ItemCode or Quantity'], 400);
        }

        $result = $this->stockSync->updateStockByItemCode((string) $itemCode, (int) $quantity);

        return response()->json($result);
    }

    private function handlePriceChange(array $data): JsonResponse
    {
        $itemCode = $data['ItemCode'] ?? $data['itemCode'] ?? null;
        $price = $data['Price'] ?? $data['price'] ?? null;

        if (! $itemCode || $price === null) {
            return response()->json(['error' => 'Missing ItemCode or Price'], 400);
        }

        $result = $this->stockSync->updatePriceByItemCode((string) $itemCode, (int) $price);

        return response()->json($result);
    }

    private function logWebhook(Request $request): void
    {
        $logDir = storage_path('logs/hesabfa-webhooks');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir.'/webhook-'.date('Y-m-d').'.log';
        $entry = [
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'body' => $request->all(),
        ];

        file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n---\n", FILE_APPEND);

        $this->cleanOldLogs($logDir);
    }

    private function cleanOldLogs(string $logDir): void
    {
        $files = glob($logDir.'/webhook-*.log');
        $cutoff = now()->subDays(30);

        foreach ($files as $file) {
            $dateStr = basename($file, '.log');
            $dateStr = str_replace('webhook-', '', $dateStr);
            try {
                $fileDate = Carbon::parse($dateStr);
                if ($fileDate->lt($cutoff)) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                // skip
            }
        }
    }
}
