<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * SOURCE: CIE_v232_Hardening_Addendum.pdf §1.3 — vector retry processor (every 5 minutes).
 */
class CieVectorRetryProcessCommand extends Command
{
    protected $signature = 'cie:vector-retry-process';

    protected $description = 'Run Python vector_retry_queue processor (queued rows with next_retry_at <= now)';

    public function handle(): int
    {
        $pythonPath = base_path('../python/run_vector_retry_queue.py');
        if (! is_readable($pythonPath)) {
            $this->error('Python runner not found at '.$pythonPath);

            return 1;
        }

        $cwd = dirname($pythonPath);
        $script = basename($pythonPath);
        $pythonExe = (string) env('PYTHON_EXECUTABLE', 'python');

        // Python job reads DB_* / OPENAI_* from the process environment only. Plain exec() does not
        // load backend/php/.env for the child, so PyMySQL often defaulted to localhost:3306 / wrong DB
        // and never cleared queued rows (dashboard stayed "Embedding Service Degraded").
        $mysql = config('database.connections.mysql', []);
        $localMode = config('services.local_llm.mode', '');
        $localModeEnv = is_bool($localMode)
            ? ($localMode ? 'true' : 'false')
            : strtolower(trim((string) $localMode));

        // Use config(), not env(), so OPENAI_* / LOCAL_LLM_* resolve when php artisan config:cache is used.
        $envOverrides = [
            'DB_HOST' => (string) ($mysql['host'] ?? env('DB_HOST', '127.0.0.1')),
            'DB_PORT' => (string) ($mysql['port'] ?? env('DB_PORT', '3306')),
            'DB_DATABASE' => (string) ($mysql['database'] ?? env('DB_DATABASE', 'cie_v232')),
            'DB_USERNAME' => (string) ($mysql['username'] ?? env('DB_USERNAME', 'root')),
            'DB_USER' => (string) ($mysql['username'] ?? env('DB_USERNAME', 'root')),
            'DB_PASSWORD' => (string) ($mysql['password'] ?? env('DB_PASSWORD', '')),
            'OPENAI_API_KEY' => (string) config('services.openai.api_key', ''),
            'OPENAI_EMBEDDING_MODEL' => (string) config('services.openai.embedding_model', 'text-embedding-3-small'),
            'LOCAL_LLM_MODE' => $localModeEnv,
            'LOCAL_LLM_BASE_URL' => (string) config('services.local_llm.base_url', ''),
        ];

        $process = new Process([$pythonExe, $script], $cwd, null, null, 300.0);
        $process->run(null, $envOverrides);

        $this->line(trim($process->getOutput().$process->getErrorOutput()));
        $code = $process->getExitCode() ?? 1;
        if ($code !== 0) {
            $this->warn('Vector retry processor exited with '.$code);

            return $code;
        }

        return 0;
    }
}
