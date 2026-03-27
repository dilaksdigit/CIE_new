<?php
namespace App\Controllers;

use App\Support\BusinessRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GscController
{
    /**
     * GET /api/v1/gsc/status — GSC connection health and last sync status.
     *
     * SOURCE: CIE_Master_Developer_Build_Spec.docx — API Route Table,
     *   Phase 2 Item 2.1, Section 9.5 Error Handling
     * Calls Python engine /gsc/verify for live GSC API verification.
     */
    public function status(): \Illuminate\Http\JsonResponse
    {
        $freshnessDays = (int) BusinessRules::get('sync.gsc_freshness_days', 8);

        $baseUrl = rtrim(env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $endpoint = $baseUrl . '/gsc/verify';

        try {
            $client = Http::timeout(30)->acceptJson();
            if ($token = env('CIE_ENGINE_TOKEN')) {
                $client = $client->withToken($token);
            }
            $response = $client->get($endpoint);
            if (!$response->successful()) {
                return $this->disconnectedResponse();
            }
            $data = $response->json();
            if (!is_array($data)) {
                return $this->disconnectedResponse();
            }

            $connected = !empty($data['connected']);
            $verified = $data['verified_properties'] ?? [];
            if (!is_array($verified)) {
                $verified = [];
            }

            $lastSync = $data['last_sync_date'] ?? null;
            if ($lastSync === '' || $lastSync === null) {
                $lastSync = null;
            } else {
                $lastSync = (string) $lastSync;
            }

            if (!$connected) {
                return response()->json([
                    'status'              => 'disconnected',
                    'verified_properties' => [],
                    'last_sync_date'      => null,
                ], 200);
            }

            $phpStatus = 'degraded';
            if ($lastSync !== null) {
                try {
                    $last = (new \DateTimeImmutable($lastSync))->setTime(0, 0, 0);
                    $today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
                    $daysSince = (int) floor(($today->getTimestamp() - $last->getTimestamp()) / 86400);
                    if ($daysSince <= $freshnessDays) {
                        $phpStatus = 'ok';
                    }
                } catch (\Throwable $e) {
                    $phpStatus = 'degraded';
                }
            }

            return response()->json([
                'status'              => $phpStatus,
                'verified_properties' => $verified,
                'last_sync_date'      => $lastSync,
            ], 200);
        } catch (\Throwable $e) {
            Log::warning('GscController status: engine unreachable: ' . $e->getMessage());

            return $this->disconnectedResponse();
        }
    }

    private function disconnectedResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status'              => 'disconnected',
            'verified_properties' => [],
            'last_sync_date'      => null,
        ], 200);
    }
}
