<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §12.1 — weekly AI citation audit (Python weekly_service).
 */
class AuditRunWeeklyCommand extends Command
{
    protected $signature = 'audit:run-weekly';

    protected $description = 'Run weekly AI citation audit for all categories (Python run_weekly_ai_audit.py)';

    public function handle(): int
    {
        // Round 2 audit C1.3 — logged GA4 ordering gate (runs after scheduled GA4 window per business_rules)
        if (Schema::hasTable('sync_status')) {
            try {
                $ga4 = DB::table('sync_status')->where('service', 'ga4')->first();
                $lastSuccess = $ga4->last_success_at ?? null;
                Log::info('audit:run-weekly — GA4 sync gate (expected after GA4 job)', [
                    'ga4_last_success_at' => $lastSuccess ? (string) $lastSuccess : null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('audit:run-weekly — could not read sync_status for GA4: '.$e->getMessage());
            }
        } else {
            Log::info('audit:run-weekly — sync_status table missing; skipping GA4 ordering check');
        }

        $this->info('Weekly AI citation audit: invoking Python runner...');

        $pythonPath = base_path('../python/run_weekly_ai_audit.py');
        if (!is_readable($pythonPath)) {
            $this->error('Python runner not found at ' . $pythonPath);

            return 1;
        }

        $cmd = sprintf('python %s 2>&1', escapeshellarg($pythonPath));
        $out = [];
        exec($cmd, $out, $code);
        $this->line(implode("\n", $out));
        if ($code !== 0) {
            $this->warn('Weekly AI audit exited with ' . $code);

            return $code;
        }

        return 0;
    }
}
