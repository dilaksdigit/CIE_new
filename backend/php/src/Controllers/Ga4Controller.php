<?php
namespace App\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Ga4Controller
{
    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §10.3, §15
     * GET /api/v1/ga4/status — GA4 health and ecommerce tracking detection.
     */
    public function status(): \Illuminate\Http\JsonResponse
    {
        $baseUrl = rtrim(env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $endpoint = $baseUrl . '/ga4/health';
        $payload = [
            'connected' => false,
            'last_sync_date' => null,
            'ecommerce_tracking_detected' => false,
            'badge' => 'disconnected',
        ];

        try {
            $client = Http::timeout(30)->acceptJson();
            if ($token = env('CIE_ENGINE_TOKEN')) {
                $client = $client->withToken($token);
            }
            $response = $client->get($endpoint);
            if ($response->successful() && is_array($response->json())) {
                $data = $response->json();
                $payload['connected'] = (bool) ($data['connected'] ?? false);
                $payload['last_sync_date'] = $data['last_sync_date'] ?? null;
                $payload['ecommerce_tracking_detected'] = (bool) ($data['ecommerce_tracking_detected'] ?? false);
                $payload['badge'] = ($payload['connected'] ? 'ok' : 'disconnected');
            }
        } catch (\Throwable $e) {
            Log::warning('Ga4Controller status engine call failed: ' . $e->getMessage());
        }

        if (Schema::hasTable('sync_status')) {
            try {
                $row = DB::table('sync_status')->where('service', 'ga4')->first();
                if ($row) {
                    $payload['badge'] = (string) ($row->status ?? $payload['badge']);
                    if (!$payload['last_sync_date'] && $row->last_success_at) {
                        $payload['last_sync_date'] = (string) $row->last_success_at;
                    }
                }
            } catch (\Throwable $e) {
                // fail-soft
            }
        }

        return response()->json($payload, 200);
    }
}
