<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TapsiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $this->logWebhook($request);

        $data = $request->all();
        $eventType = $data['eventType'] ?? $data['EventType'] ?? $data['event_type'] ?? null;

        Log::info('[TapsiWebhook] Received', [
            'event_type' => $eventType,
            'keys' => array_keys($data),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
        ]);
    }

    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'وب‌هوک تپسی شاپ فعال است',
        ]);
    }

    private function logWebhook(Request $request): void
    {
        $logDir = storage_path('logs/tapsi-webhooks');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir.'/webhook-'.date('Y-m-d').'.log';
        $entry = [
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body' => $request->all(),
            'raw_body' => $request->getContent(),
        ];

        file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n---\n", FILE_APPEND);

        $this->cleanOldLogs($logDir);
    }

    private function cleanOldLogs(string $logDir): void
    {
        $files = glob($logDir.'/webhook-*.log');
        $cutoff = now()->subDays(30);

        foreach ($files as $file) {
            $dateStr = str_replace('webhook-', '', basename($file, '.log'));
            try {
                if (Carbon::parse($dateStr)->lt($cutoff)) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                // skip
            }
        }
    }
}
