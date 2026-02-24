<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Services\DecayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CIE v2.3.2 — Decay escalation from weekly audit citation scores.
 * Run weekly after AI audit (e.g. cron or schedule cie:decay-check).
 * Reads latest quorum-met audit run, computes citation score per Hero SKU,
 * increments decay_consecutive_zeros on zero scores, transitions decay_status
 * to auto_brief at count ≥ 3, and creates a brief record via DecayService.
 */
class CieDecayCheckCommand extends Command
{
    protected $signature = 'cie:decay-check';
    protected $description = 'Process citation decay: zero-score weeks → yellow_flag/alert/auto_brief; create brief at week 3';

    public function __construct(private DecayService $decayService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('ai_audit_runs') || !Schema::hasTable('ai_audit_results')) {
            $this->warn('Tables ai_audit_runs / ai_audit_results not found. Skip decay check.');
            return 0;
        }

        $run = DB::table('ai_audit_runs')
            ->where('quorum_met', true)
            ->orderByDesc('run_date')
            ->orderByDesc('created_at')
            ->first();

        if (!$run) {
            $this->info('No quorum-met audit run found. Nothing to process.');
            return 0;
        }

        $quorumStatus = 'GO';
        $heroSkus = Sku::whereIn('tier', ['hero', 'HERO', 'Hero'])->get();
        if ($heroSkus->isEmpty()) {
            $this->info('No Hero SKUs. Nothing to process.');
            return 0;
        }

        $processed = 0;
        foreach ($heroSkus as $sku) {
            $citationSum = (int) DB::table('ai_audit_results')
                ->where('run_id', $run->run_id)
                ->where('cited_sku_id', $sku->id)
                ->sum('score');

            $this->decayService->processWeeklyDecay($sku, $citationSum, $quorumStatus);
            $processed++;
        }

        $this->info("Decay check complete. Run {$run->run_id} (quorum met); {$processed} Hero SKUs processed.");
        return 0;
    }
}
