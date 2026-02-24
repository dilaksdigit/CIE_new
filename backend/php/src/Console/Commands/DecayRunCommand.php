<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * CIE v2.3.2 — Decay escalation scheduler.
 * Run weekly after AI audit (e.g. cron: 0 6 * * 1 cd /path/to/backend/php && php artisan decay:run).
 * Optionally invokes Python run_decay_escalation if PYTHON_PATH is set.
 */
class DecayRunCommand extends Command
{
    protected $signature = 'decay:run {--php-only : Use only PHP DecayService (no Python)}';
    protected $description = 'Run citation decay escalation (week 1–4 flags, week 3 auto-brief)';

    public function handle(): int
    {
        $this->info('Decay escalation: checking for Python runner...');

        $pythonPath = base_path('../python/run_decay_escalation.py');
        if (!$this->option('php-only') && is_readable($pythonPath)) {
            $cmd = sprintf('python %s 2>&1', escapeshellarg($pythonPath));
            $out = [];
            exec($cmd, $out, $code);
            $this->line(implode("\n", $out));
            if ($code !== 0) {
                $this->warn('Python decay runner exited with ' . $code . '. Run with --php-only or fix DB/Python env.');
            }
            return $code === 0 ? 0 : 1;
        }

        if ($this->option('php-only')) {
            $this->info('PHP-only mode: use DecayService::processWeeklyDecay() per SKU when citation scores are available.');
            $this->comment('For full escalation (zero-week detection + auto-brief), run: python backend/python/run_decay_escalation.py');
            return 0;
        }

        $this->warn('Python runner not found at ' . $pythonPath . '. Schedule: python run_decay_escalation.py weekly.');
        return 0;
    }
}
