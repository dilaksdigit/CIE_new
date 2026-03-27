<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §12.1 — weekly AI citation audit (Python weekly_service).
 */
class AuditRunWeeklyCommand extends Command
{
    protected $signature = 'audit:run-weekly';

    protected $description = 'Run weekly AI citation audit for all categories (Python run_weekly_ai_audit.py)';

    public function handle(): int
    {
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
