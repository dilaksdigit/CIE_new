<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8 — 500ms validation SLA

namespace App\Http\Middleware;

use App\Support\BusinessRules;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidateSlaMiddleware
{
    private const SLA_MS_DEFAULT = 500;

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $response = $next($request);
        $elapsedMs = (microtime(true) - $startTime) * 1000;

        try {
            $slaThreshold = BusinessRules::get('gates.validate_sla_ms', self::SLA_MS_DEFAULT);
        } catch (\Throwable $e) {
            $slaThreshold = self::SLA_MS_DEFAULT;
        }

        $response->headers->set('X-CIE-Validate-Ms', round($elapsedMs, 2));
        if ($elapsedMs > (float) $slaThreshold) {
            Log::warning('CIE validate endpoint exceeded SLA', [
                'path' => $request->path(),
                'elapsed_ms' => round($elapsedMs, 2),
                'sla_ms' => $slaThreshold,
                'sku_id' => $request->route('sku_id'),
            ]);
            $response->headers->set('X-CIE-SLA-Breach', 'true');
        }

        return $response;
    }
}
